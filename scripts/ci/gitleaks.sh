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
# Exit: 0 clean · 1 secrets found
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT" || exit 1

IMG_GITLEAKS="${IMG_GITLEAKS:-zricethezav/gitleaks:latest}"

# Everything this project authors and commits.
TARGETS=(
    docker
    scripts
    app/settings.d
    .env.example
    .gitlab-ci.yml
    docker-compose.yml
    publiccode.yml
    hdp.sh
)

run_gitleaks() {
    local target="$1"
    if command -v gitleaks >/dev/null 2>&1; then
        gitleaks dir "$target" --no-banner --redact 2>&1
    else
        docker run --rm -v "$REPO_ROOT":/repo -w /repo "$IMG_GITLEAKS" \
            dir "$target" --no-banner --redact 2>&1
    fi
}

if ! command -v gitleaks >/dev/null 2>&1 && ! docker info >/dev/null 2>&1; then
    echo "gitleaks.sh: neither a gitleaks binary nor a usable docker daemon" >&2
    exit 1
fi

fails=0
for t in "${TARGETS[@]}"; do
    [ -e "$t" ] || continue
    out="$(run_gitleaks "$t")"
    if printf '%s' "$out" | grep -q 'leaks found:'; then
        echo "  SECRETS in $t:"
        printf '%s\n' "$out" | sed 's/^/    /'
        fails=$((fails + 1))
    fi
done

if [ "$fails" -gt 0 ]; then
    echo ""
    echo "  A committed secret must be treated as compromised: rotate it first,"
    echo "  then remove it from the file. Rewriting history alone is not enough."
    echo "  If a finding is genuinely not a secret, add its fingerprint and the"
    echo "  reasoning to .gitleaksignore."
    exit 1
fi
echo "  no secrets in ${#TARGETS[@]} owned paths"
exit 0
