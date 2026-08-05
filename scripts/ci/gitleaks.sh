#!/usr/bin/env bash
# ============================================================
# T0 — secret scanning.
#
# `git log` records commit 5d2530046, "fix(security): widen .gitignore to cover
# .env siblings", whose message documents that .env.bak-preexisting — 7,008
# bytes holding a live Infisical client secret, the LLM API key, and the DB root
# and admin passwords — was sitting untracked next to .env, one `git add -A`
# from publication. The hole is closed, but this is a repository being published
# as open source whose recent history is a near-miss on exactly this.
#
# Scope is the same ownership boundary every other gate uses: paths this project
# authors. That is not indifference to upstream — it is that scanning the
# vendored MediaWiki and BlueSpice trees takes 3m37s over 425 MB and returns 30
# findings, every one a false positive (American Sign Language notation in
# app/languages/i18n/ase.json, PEM header strings inside key *parsers*, README
# examples, upstream test vectors). A gate that reports 30 false positives is a
# gate somebody disables.
#
# Uses gitleaks' default ruleset deliberately — no custom .gitleaks.toml. A
# repo-root config is auto-loaded by gitleaks and is an easy way to silently
# disable rule loading altogether; reviewed exceptions live in .gitleaksignore,
# keyed by path:rule:line so they expire the moment the code moves.
#
# ─── Why the exit code, and not the output ──────────────────────────
#
# This gate used to decide by grepping the output for `leaks found:` and
# treating its absence as clean. Everything that stops gitleaks from printing
# that string — an image-pull failure, a docker daemon error, an OOM, a release
# renaming the `dir` subcommand or the summary line — reported "no secrets" and
# exited 0. That is the failure scripts/ci/composer-audit.sh names in its own
# header as the one this class of gate must not have, and it was worse here
# because IMG_GITLEAKS was an explicitly moving `:latest`.
#
# So: branch on the exit status (0 clean, 1 leaks, anything else could not run
# and is a hard failure), and require the *positive* `no leaks found` marker
# before believing a 0. A scanner that exits 0 without saying it scanned
# anything is a scanner whose contract has changed, and the only safe reading of
# that is "unknown", not "clean".
#
# The image is pinned by digest for the same reason bats.sh pins one: a moving
# tag means the thing that decides whether this repository publishes a secret
# can change under it between two runs of the same commit. Bump it deliberately
# — `docker image inspect zricethezav/gitleaks:vX.Y.Z --format
# '{{index .RepoDigests 0}}'` — and let the marker check catch a contract change.
#
# Exit: 0 clean · 1 secrets found · 2 could not run
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT" || exit 1

# gitleaks v8.30.1. Digest, not tag — see the header.
IMG_GITLEAKS="${IMG_GITLEAKS:-zricethezav/gitleaks@sha256:c00b6bd0aeb3071cbcb79009cb16a60dd9e0a7c60e2be9ab65d25e6bc8abbb7f}"

# The line gitleaks prints when a scan completed and found nothing. Absence of
# it is never read as clean; see the header.
CLEAN_MARKER='no leaks found'

# Everything this project authors and commits.
#
# `.github/workflows/` is first for a reason: CI definitions are the canonical
# place a hardcoded token lands, they are project-authored, and this repository
# gained six of them without any of them being scanned. `tests/`, `docs/`,
# `VERSIONS.yml` and `docker-compose.prod.yml` were outside the boundary for no
# reason other than that they did not exist when the list was written. A gate
# whose scope lags the tree is a gate that quietly covers less every month.
TARGETS=(
    .github/workflows
    docker
    scripts
    tests
    docs
    app/settings.d
    .env.example
    .gitlab-ci.yml
    docker-compose.yml
    docker-compose.prod.yml
    publiccode.yml
    VERSIONS.yml
    hdp.sh
)

if command -v gitleaks >/dev/null 2>&1; then
    RUNNER=binary
elif docker info >/dev/null 2>&1; then
    RUNNER=docker
else
    echo "gitleaks.sh: neither a gitleaks binary nor a usable docker daemon" >&2
    exit 2
fi

run_gitleaks() {
    local target="$1"
    if [ "$RUNNER" = binary ]; then
        gitleaks dir "$target" --no-banner --redact 2>&1
    else
        docker run --rm -v "$REPO_ROOT":/repo -w /repo "$IMG_GITLEAKS" \
            dir "$target" --no-banner --redact 2>&1
    fi
}

# Recorded in the log so a future failure can be read against the version that
# produced it. Not asserted: a contributor's binary is allowed to differ from
# the pinned image, and the marker check below is what catches a real contract
# change either way.
version="$(if [ "$RUNNER" = binary ]; then gitleaks version 2>&1; else
    docker run --rm "$IMG_GITLEAKS" version 2>&1; fi | tail -1)" || version='unknown'

leaks=0        # targets where gitleaks found something
broken=0       # targets where gitleaks could not be believed
scanned=0
for t in "${TARGETS[@]}"; do
    # A target that no longer exists is not a target that is clean. Every path
    # in TARGETS is committed, so a miss means the file moved and the scope
    # quietly shrank — the same silent-pass this gate exists to refuse.
    if [ ! -e "$t" ]; then
        echo "  MISSING target '$t' — it is in TARGETS but not in the tree."
        echo "    Either restore it or remove it from TARGETS deliberately;"
        echo "    a path that vanishes must not shrink the scan in silence."
        broken=$((broken + 1))
        continue
    fi

    out="$(run_gitleaks "$t")"
    rc=$?

    case "$rc" in
        0)
            if printf '%s\n' "$out" | grep -qF "$CLEAN_MARKER"; then
                scanned=$((scanned + 1))
            else
                # Exit 0 with no "no leaks found": the scan did not complete, or
                # gitleaks changed what it prints. Either way this is unknown.
                echo "  CANNOT VERIFY $t: gitleaks exited 0 without reporting '$CLEAN_MARKER'."
                printf '%s\n' "$out" | sed 's/^/    /'
                broken=$((broken + 1))
            fi
            ;;
        1)
            echo "  SECRETS in $t:"
            printf '%s\n' "$out" | sed 's/^/    /'
            leaks=$((leaks + 1))
            ;;
        *)
            echo "  CANNOT RUN on $t: gitleaks exited $rc."
            printf '%s\n' "$out" | sed 's/^/    /'
            broken=$((broken + 1))
            ;;
    esac
done

if [ "$leaks" -gt 0 ]; then
    echo ""
    echo "  A committed secret must be treated as compromised: rotate it first,"
    echo "  then remove it from the file. Rewriting history alone is not enough."
    echo "  If a finding is genuinely not a secret, add its fingerprint and the"
    echo "  reasoning to .gitleaksignore."
    exit 1
fi

if [ "$broken" -gt 0 ]; then
    echo ""
    echo "  $broken of ${#TARGETS[@]} owned paths were not scanned, so this gate has"
    echo "  no opinion about them. It fails rather than reporting the ones that did"
    echo "  scan as though they were the whole scope — 'we could not look' and"
    echo "  'we looked and it is clean' are not the same answer."
    echo "  gitleaks: $version (image $IMG_GITLEAKS)"
    exit 2
fi

echo "  no secrets in $scanned owned paths — gitleaks $version"
exit 0
