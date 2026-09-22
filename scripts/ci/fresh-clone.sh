#!/usr/bin/env bash
# ============================================================
# TF — the fresh-clone gate.
#
# Clones this repository at HEAD into a throwaway directory and asserts that
# everything `docker/setup.sh` needs is actually committed, is not swallowed by
# a .gitignore, and that nothing secret came along for the ride.
#
# Why this exists, and why it runs before the expensive tiers:
# docs/QA-REPORT.md records seven bugs, six of them critical, and states that
# each "was invisible in the development checkout … and only surfaced on a
# genuinely fresh clone". Bug 1 was the entire docker/ tree never being
# committed. Bug 2 was app/skins/.gitignore's blanket /* silently eating a
# required skin file. Neither is visible to any test that runs in the
# maintainer's working tree, because in that tree the files are simply there.
#
# It clones the repo under test rather than a hardcoded URL, so it is
# forge-agnostic and keeps working if CI moves between openCode, GitLab and
# GitHub.
#
# No containers, no database, no .env. ~15s.
#
# Exit: 0 all assertions hold · 1 at least one failed
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || { echo "fresh-clone.sh: not inside a git repository" >&2; exit 1; }
cd "$REPO_ROOT" || exit 1

if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
    C_RED=$'\033[0;31m'; C_GRN=$'\033[0;32m'; C_DIM=$'\033[0;90m'; C_BLD=$'\033[1m'; C_OFF=$'\033[0m'
else
    C_RED=''; C_GRN=''; C_DIM=''; C_BLD=''; C_OFF=''
fi

FAILS=0
ok()   { printf '  %sok%s   %s\n' "$C_GRN" "$C_OFF" "$1"; }
bad()  { printf '  %sFAIL%s %s\n' "$C_RED" "$C_OFF" "$1"; FAILS=$((FAILS+1)); }

# ─── Required inputs ────────────────────────────────────────────────
# Every entry is something setup.sh, docker compose, or the installer reads.
# A missing entry means a stranger's clone cannot produce a working wiki.

# Deployment surface. QA Bug 1 was this entire group being absent.
REQUIRED_FILES=(
    docker-compose.yml
    # The GPU override. install.sh names it in the start command whenever the
    # operator picks GPU inference, so a clone without it turns a configured
    # install into "no such file or directory" at the first `up`.
    docker-compose.gpu.yml
    .env.example
    .gitignore
    docker/setup.sh
    docker/infisical-loader.sh
    docker/chatbot-proxy/server.py
    docker/haystack/hdp_api_server.py
    docker/haystack/serialization.py
    docker/haystack/hdp_pipeline.yaml
    docker/haystack/entrypoint.sh
    docker/haystack/ingest_hdp_wiki.py
    docker/haystack/wikitext.py
    docker/haystack/render_pipeline.py
    install.sh
    update.sh
    hdp.sh
    scripts/check.sh
    scripts/ci/bats.sh
    # This fork's declared version, and the input to both the
    # version-consistency gate and the release-watch job. A clone without it
    # cannot answer "what version are we", which is where the four-way skew
    # came from.
    VERSIONS.yml
    scripts/lib/versions.py
    scripts/ci/version-consistency.sh
    # Track A: the CVE gate and the advisories this fork knowingly carries.
    # The baseline is what keeps the gate honest rather than permanently red.
    scripts/ci/composer-audit.sh
    scripts/lib/audit_baseline.py
    docker/ci/composer-audit-baseline.json
    renovate.json
    # Track B: the only thing that can notice a MediaWiki core security
    # release, since core is vendored source and no lockfile bump will ever
    # mention it.
    scripts/ci/release-watch.sh
    scripts/lib/release_watch.py
    scripts/ci/pytest.sh
    # The two container jobs and the plumbing they share. Both CI workflows are
    # thin callers of these, so a clone without them has a pipeline that cannot
    # run and a contributor who cannot reproduce it.
    scripts/ci/t3-integration.sh
    scripts/ci/t4-smoke.sh
    scripts/ci/lib/stack.sh
    # T5: the migration job and the snapshot it upgrades. The fixture is the
    # input — without it t5-migration.sh exits 2 and the upgrade path is
    # untested again.
    scripts/ci/t5-migration.sh
    scripts/ci/make-db-fixture.sh
    docker/ci/fixtures/seeded-wiki.sql.gz
    docker/ci/fixtures/seeded-wiki.meta.json
    # T4's CI-only compose overlay: the buildx layer cache and the HuggingFace
    # model cache. `t4-smoke.sh --cache` exits 2 without it.
    docker/ci/compose.cache.yml
    # The published-image deployment path. README-DOCKER.md's Quick Start
    # offers it as the alternative to a ~5 minute build, so a clone without it
    # has a documented command that fails on the file not existing.
    docker-compose.prod.yml
    # The same path on a GPU host, and the only one that pulls the `-gpu`
    # haystack image release.yml publishes. install.sh names this file whenever
    # the operator picks GPU inference *and* pre-built images — the combination
    # that used to be refused — so a clone without it turns that answer into "no
    # such file or directory" at the first `up`.
    docker-compose.prod-gpu.yml
    scripts/convert-docs.sh
    # convert-docs.sh exits 1 without this — it owns the page mapping, the
    # source list and the post-processor, so a clone missing it cannot
    # regenerate docker/mediawiki/wiki-docs/ at all.
    scripts/lib/convert_docs_postprocess.py

    # Bind-mounted into the mediawiki container by docker-compose.yml. Absent,
    # docker creates a *directory* at the mount point and FPM falls back to the
    # image default pool: nobody:nogroup instead of www-data, which does not
    # match the file ownership setup.sh establishes.
    docker/wiki/www.conf

    # MediaWiki inputs consumed by setup.sh's composer stage.
    app/composer.json
    app/composer.lock
    app/composer.local.json

    # The pre-autoload-dump chain that composer dump-autoload fires.
    app/_bluespice/pre-autoload-dump.sh
    app/_bluespice/pre-autoload-dump.d/99-apply_patches.sh
    app/_bluespice/pre-autoload-dump.d/10-add_tools.sh
    app/_bluespice/pre-autoload-dump.d/05-add_installer_overrides.sh

    # Class-A patch targets. Present in the clone, patched later by setup.sh.
    app/extensions/BlueSpiceExtendedSearch/src/Backend.php
    app/extensions/BlueSpiceExtendedSearch/resources/ext.blueSpiceExtendedSearch.SearchCenter.js

    # Two canaries under app/skins/, which is the tree with the blanket /*
    # exclusion. HookRunner.php is QA Bug 2 itself. FeatureManagerFactory.php
    # is the file whose absence made Special:Preferences return 500 for every
    # logged-in user until Wave 0 restored the vendored Vector skin — a second
    # instance of the same class, found the same way.
    app/skins/Vector/includes/Hooks/HookRunner.php
    app/skins/Vector/includes/FeatureManagement/FeatureManagerFactory.php
    app/skins/.gitignore
)

REQUIRED_DIRS=(
    docker
    docker/mediawiki/wiki-docs      # seeded Help pages; setup.sh edit.php's these in
    docker/opensearch
    docker/mariadb
    app/settings.d                  # the 17 files gating ~130 extensions
    app/_bluespice/patches          # the 17 inherited .diff files
    app/mw-config/overrides         # installer overrides; destroyed on every run until Wave 0
    scripts/ci
)

# Directories that must contain at least N files, because an empty directory is
# not an error to `test -d` but is fatal to the install.
declare -A REQUIRED_MIN_COUNT=(
    [app/settings.d]=17
    [app/_bluespice/patches]=17
    [app/mw-config/overrides]=19
    [docker/mediawiki/wiki-docs]=1
    [app/_bluespice/pre-autoload-dump.d]=8
)

# Must NOT be in the clone. A hit here means a secret or a generated file was
# committed. `git log` records a near-miss on exactly this: .env.bak-preexisting
# held a live Infisical client secret and was one `git add -A` from publication.
FORBIDDEN=(
    .env
    app/LocalSettings.php
    app/vendor
)

# app/cache/ is not listed above because upstream MediaWiki legitimately ships
# cache/.htaccess, the rule that denies web access to the directory. Everything
# else under it is runtime state — l10n caches, the SQLite job queue, session
# data — and must never be committed. Checked by exception below.
CACHE_ALLOWED_RE='^app/cache/\.htaccess$'

# ─── Clone HEAD into a clean directory ──────────────────────────────
WORK="$(mktemp -d)"
cleanup() { rm -rf "$WORK"; }
trap cleanup EXIT

HEAD_SHA="$(git rev-parse --short HEAD)"
echo ""
echo "${C_BLD}TF — fresh-clone gate${C_OFF}  ${C_DIM}(HEAD $HEAD_SHA)${C_OFF}"
echo ""

# file:// forces a real clone rather than a hardlinked shortcut, so what lands
# in $WORK is exactly the committed tree — not the working tree, which is the
# environment already known not to reproduce these bugs.
if ! git clone --quiet --no-hardlinks "file://$REPO_ROOT" "$WORK/clone" 2>"$WORK/clone.err"; then
    echo "  ${C_RED}FAIL${C_OFF} could not clone the repository at HEAD"
    sed 's/^/        /' "$WORK/clone.err"
    exit 1
fi
CLONE="$WORK/clone"

if [ -n "$(git status --porcelain 2>/dev/null)" ]; then
    echo "  ${C_DIM}note: your working tree has uncommitted changes; they are"
    echo "        deliberately NOT under test here — only committed state is.${C_OFF}"
    echo ""
fi

# ─── Assertions ─────────────────────────────────────────────────────
echo "${C_BLD}Required files${C_OFF}"
for f in "${REQUIRED_FILES[@]}"; do
    if [ -f "$CLONE/$f" ]; then ok "$f"; else bad "$f — missing from a fresh clone"; fi
done

echo ""
echo "${C_BLD}Required directories${C_OFF}"
for d in "${REQUIRED_DIRS[@]}"; do
    if [ ! -d "$CLONE/$d" ]; then
        bad "$d/ — missing from a fresh clone"
        continue
    fi
    want="${REQUIRED_MIN_COUNT[$d]:-}"
    if [ -n "$want" ]; then
        got=$(find "$CLONE/$d" -type f | wc -l)
        if [ "$got" -ge "$want" ]; then
            ok "$d/ ($got files, need >= $want)"
        else
            bad "$d/ has $got files, expected at least $want"
        fi
    else
        ok "$d/"
    fi
done

# pre-autoload-dump.d is checked for count but is not in REQUIRED_DIRS above,
# because it is reached through its parent; assert it explicitly.
if [ -d "$CLONE/app/_bluespice/pre-autoload-dump.d" ]; then
    got=$(find "$CLONE/app/_bluespice/pre-autoload-dump.d" -name '*.sh' | wc -l)
    [ "$got" -ge 8 ] && ok "app/_bluespice/pre-autoload-dump.d/ ($got scripts)" \
                     || bad "app/_bluespice/pre-autoload-dump.d/ has $got scripts, expected >= 8"
else
    bad "app/_bluespice/pre-autoload-dump.d/ — missing"
fi

echo ""
echo "${C_BLD}Must not be committed${C_OFF}"
for f in "${FORBIDDEN[@]}"; do
    if [ -e "$CLONE/$f" ]; then
        bad "$f — present in the clone; a secret or generated file was committed"
    else
        ok "$f absent"
    fi
done
# Any .env sibling other than the template.
stray="$(find "$CLONE" -maxdepth 1 -name '.env*' ! -name '.env.example' -print 2>/dev/null)"
if [ -n "$stray" ]; then
    bad "stray .env sibling committed: $(echo "$stray" | tr '\n' ' ')"
else
    ok "no stray .env siblings"
fi

# Runtime state under app/cache/, excluding upstream's own .htaccess.
cache_extra="$(git -C "$CLONE" ls-files app/cache | grep -vE "$CACHE_ALLOWED_RE" || true)"
if [ -n "$cache_extra" ]; then
    bad "runtime state committed under app/cache/: $(echo "$cache_extra" | tr '\n' ' ')"
else
    ok "app/cache/ holds nothing but upstream's .htaccess"
fi

echo ""
echo "${C_BLD}Not swallowed by .gitignore${C_OFF}"
# The failure mode QA Bug 2 documents is a file that exists on the maintainer's
# disk, is required at runtime, and is silently un-addable. Checking the paths
# against the clone's own ignore rules catches a future .gitignore edit that
# would make a required file untrackable, even while the file is still present.
ignored=0
for f in "${REQUIRED_FILES[@]}"; do
    if git -C "$CLONE" check-ignore -q --no-index "$f" 2>/dev/null; then
        bad "$f is matched by a .gitignore rule — it cannot be re-added if removed"
        ignored=$((ignored+1))
    fi
done
for d in "${REQUIRED_DIRS[@]}"; do
    if git -C "$CLONE" check-ignore -q --no-index "$d" 2>/dev/null; then
        bad "$d/ is matched by a .gitignore rule"
        ignored=$((ignored+1))
    fi
done
[ "$ignored" -eq 0 ] && ok "all ${#REQUIRED_FILES[@]} files and ${#REQUIRED_DIRS[@]} directories are trackable"

echo ""
echo "${C_BLD}Machine-readable inputs parse${C_OFF}"
# renovate.json is here for a reason a schema check would miss: Renovate reads
# its config from the default branch and simply does nothing useful if the file
# will not parse, with no failure visible in this repository at all.
for j in app/composer.json app/composer.lock app/composer.local.json \
         renovate.json docker/ci/composer-audit-baseline.json; do
    if [ -f "$CLONE/$j" ]; then
        if python3 -c "import json,sys; json.load(open(sys.argv[1]))" "$CLONE/$j" 2>/dev/null; then
            ok "$j is valid JSON"
        else
            bad "$j is not valid JSON"
        fi
    fi
done
# .env.example must define every variable docker-compose.yml interpolates,
# otherwise `cp .env.example .env` produces a stack with empty settings. This
# is a cheap subset of the dedicated env-example check.
# Only variables used WITHOUT a default need to be declared. Compose expands
# ${VAR:-fallback} on its own, so requiring those in .env.example would flag
# every tunable the template deliberately leaves commented out (the memory
# limits, HDP_BIND_ADDR). A bare ${VAR} with nothing in .env.example is the
# real defect: compose substitutes the empty string and exits 0, so the stack
# comes up silently misconfigured rather than failing.
missing_vars=""
while IFS= read -r v; do
    [ -n "$v" ] || continue
    grep -qE "^${v}=" "$CLONE/.env.example" 2>/dev/null || missing_vars="$missing_vars $v"
done < <(grep -oE '\$\{[A-Z_][A-Z0-9_]*\}' "$CLONE/docker-compose.yml" 2>/dev/null \
         | sed -E 's/^\$\{([A-Z_0-9]+)\}$/\1/' | sort -u)
if [ -n "$missing_vars" ]; then
    bad ".env.example lacks variables docker-compose.yml uses with no default:$missing_vars"
else
    ok ".env.example declares every no-default \${VAR} in docker-compose.yml"
fi

# ─── Verdict ────────────────────────────────────────────────────────
echo ""
if [ "$FAILS" -eq 0 ]; then
    echo "  ${C_GRN}TF PASS${C_OFF} — a fresh clone of $HEAD_SHA has everything setup.sh needs."
    echo ""
    exit 0
fi
echo "  ${C_RED}TF FAIL${C_OFF} — $FAILS assertion(s) failed."
echo ""
echo "  A stranger cloning this commit would not get a working wiki."
echo "  If a file exists on your disk but failed above, it is untracked or"
echo "  ignored: check 'git status --ignored' and 'git check-ignore -v <path>'."
echo ""
exit 1
