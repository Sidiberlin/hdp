#!/usr/bin/env bash
# ============================================================
# check.sh — the one command to run before you push.
#
#   ./scripts/check.sh              everything that does not need a stack (<3 min)
#   ./scripts/check.sh --fix        the same, applying auto-fixes where possible
#   ./scripts/check.sh --list       show the checks and what each one needs
#   ./scripts/check.sh --only ruff  run one check (repeatable)
#
# It returns the same verdict as the CI lint stage, without asking you to
# install a toolchain, copy a .env, or start the 7-container stack. Every check
# prefers a binary already on your PATH and otherwise runs the same pinned
# container image CI uses; anything it cannot run either way is reported as
# SKIPPED rather than quietly passing.
#
# Exit codes:  0 everything passed (skips are not failures)
#              1 at least one check failed
#              2 bad usage
# ============================================================
set -uo pipefail

# ─── Pinned toolchain ───────────────────────────────────────────────
# These must stay identical to the images named in .gitlab-ci.yml, otherwise
# this script and CI can disagree and the whole point is lost.
#
# Two of them are moving tags (:stable, :latest) because that is what CI
# currently pins. That means CI's own verdict can change without a commit —
# worth fixing, but it has to be fixed in both places at once, so it is left
# to the .gitlab-ci.yml pass rather than silently diverging here.
IMG_SHELLCHECK="koalaman/shellcheck-alpine:stable"
IMG_YAMLLINT="cytopia/yamllint:latest"
IMG_RUFF="ghcr.io/astral-sh/ruff:0.16.1"
IMG_PHP="php:8.3-cli"
# The pytest tiers fall back to this when the host cannot satisfy them. Kept in
# step with scripts/ci/pytest.sh and docker/haystack/Dockerfile's base image.
IMG_PYTHON="python:3.11-slim"
# Pinned by digest, not by :latest — see the note above about moving tags.
IMG_BATS="bats/bats@sha256:5322b877351fda0cc435de8c6116de7d0a2ec79d7c680132a0ef329a633bc66f"
# composer audit reads app/composer.lock against the packagist advisory
# database. Kept in step with scripts/ci/composer-audit.sh and .gitlab-ci.yml.
IMG_COMPOSER="composer:2.8"

# Host binaries are a convenience, not the source of truth. When a host tool's
# version differs from the pinned image the verdicts can differ too, so say so
# instead of letting someone chase a discrepancy CI will not reproduce.
RUFF_PINNED_VERSION="0.16.1"

YAMLLINT_RULES='{extends: default, rules: {line-length: disable, document-start: disable, truthy: disable}}'
YAML_FILES=(docker-compose.yml docker/ci/compose.cache.yml publiccode.yml VERSIONS.yml docker/haystack/hdp_pipeline.yaml .gitlab-ci.yml .github/workflows/)

# ─── Locate the repo ────────────────────────────────────────────────
REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
if [ -z "$REPO_ROOT" ]; then
    REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
fi
cd "$REPO_ROOT" || { echo "check.sh: cannot cd to repo root" >&2; exit 2; }

# ─── Output ─────────────────────────────────────────────────────────
if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
    C_RED=$'\033[0;31m'; C_GRN=$'\033[0;32m'; C_YEL=$'\033[0;33m'
    C_DIM=$'\033[0;90m'; C_BLD=$'\033[1m'; C_OFF=$'\033[0m'
else
    C_RED=''; C_GRN=''; C_YEL=''; C_DIM=''; C_BLD=''; C_OFF=''
fi

FIX=0
PATCHES=0
INTEGRATION=0
SMOKE=0
VERBOSE=0
NO_DOCKER=0
declare -a ONLY=()

# Every check name --only will accept. Kept next to the `run_check` calls at the
# bottom of this file, and validated against, because an --only value matching
# nothing used to run zero checks and then print "0 passed" followed by "OK —
# this is the same verdict the CI lint stage will give", exit 0.
#
# That is a false green, and it contradicts this script's headline property:
# skips are never passes and the closing line says so. Two ways in — a typo, or
# the natural-but-unsupported comma form `--only shellcheck,yamllint`. The comma
# form is now split and accepted; anything unmatched is a usage error.
#
# It matters more since .github/workflows/ci.yml drives this script with one
# `--only` per job: a mistyped matrix entry would otherwise be a permanently
# green job that runs nothing at all.
KNOWN_CHECKS=(
    shellcheck yamllint ruff pytest-unit pytest-haystack bats php-lint compose
    gitleaks env-example publiccode patch-ignore manifest versions composer-audit
    fresh-clone patches integration smoke
)

PASSED=(); FAILED=(); SKIPPED=()
declare -A SKIP_REASON=()

usage() {
    cat <<EOF
${C_BLD}check.sh${C_OFF} — run the CI lint gate locally, in under three minutes.

  ./scripts/check.sh                 run every check
  ./scripts/check.sh --fix           apply auto-fixes where the tool supports it
  ./scripts/check.sh --only ruff     run one check (repeatable)
  ./scripts/check.sh --list          list checks and their requirements
  ./scripts/check.sh --no-docker     host binaries only; skip what is unavailable
  ./scripts/check.sh --verbose       show tool output even when a check passes
  ./scripts/check.sh --patches       + full patch verification (composer-installed tree)
  ./scripts/check.sh --integration   + the Wave 3 tier (needs a running, installed wiki)
  ./scripts/check.sh --smoke         + the Wave 4 tier (needs the full 7-container stack)

No .env, no running stack, and no toolchain install required. Checks that can
run neither from PATH nor from a pinned image are reported SKIPPED, never
passed.
EOF
}

list_checks() {
    printf '%-16s %-34s %s\n' CHECK WHAT REQUIRES
    printf '%-16s %-34s %s\n' ----- ---- --------
    printf '%-16s %-34s %s\n' shellcheck  'shell in docker/ scripts/ hdp.sh' "shellcheck | $IMG_SHELLCHECK"
    printf '%-16s %-34s %s\n' yamllint    'compose, publiccode, pipeline, CI' "yamllint | $IMG_YAMLLINT"
    printf '%-16s %-34s %s\n' ruff        'python under docker/ scripts/ tests/' "ruff | $IMG_RUFF"
    printf '%-16s %-34s %s\n' pytest-unit 'stdlib unit tests (~2s)' "pytest | $IMG_PYTHON"
    printf '%-16s %-34s %s\n' pytest-haystack 'to_native + load_pipeline' "haystack-ai | $IMG_PYTHON"
    printf '%-16s %-34s %s\n' bats        'infisical-loader.sh behaviour' "bats | ${IMG_BATS%%@*}"
    printf '%-16s %-34s %s\n' php-lint    'syntax of app/settings.d/*.php' "php | $IMG_PHP"
    printf '%-16s %-34s %s\n' compose     'docker-compose.yml interpolates' 'docker compose v2'
    printf '%-16s %-34s %s\n' gitleaks    'no secrets in owned paths' 'gitleaks | zricethezav/gitleaks'
    printf '%-16s %-34s %s\n' env-example '.env.example covers compose' 'grep'
    printf '%-16s %-34s %s\n' publiccode  'publiccode.yml schema' 'italia/publiccode-parser-go'
    printf '%-16s %-34s %s\n' patch-ignore 'no patch target is gitignored' 'git'
    printf '%-16s %-34s %s\n' manifest    'patch manifest schema' 'python3 + pyyaml'
    printf '%-16s %-34s %s\n' versions    'VERSIONS.yml matches the tree' 'python3'
    printf '%-16s %-34s %s\n' composer-audit 'new CVEs in app/composer.lock' "composer | $IMG_COMPOSER"
    printf '%-16s %-34s %s\n' fresh-clone 'TF: fresh clone has every input' 'git'
    printf '%-16s %-34s %s\n' patches     'all 19 patches present (--patches)' 'patch(1)'
    printf '%-16s %-34s %s\n' integration 'live wiki (--integration)' 'a running, installed stack'
    printf '%-16s %-34s %s\n' smoke       'search + chatbot (--smoke)' 'the full 7-container stack'
}

while [ $# -gt 0 ]; do
    case "$1" in
        --fix)        FIX=1 ;;
        --verbose|-v) VERBOSE=1 ;;
        --no-docker)  NO_DOCKER=1 ;;
        --only)       shift; [ $# -gt 0 ] || { echo "--only needs a name" >&2; exit 2; }
                      # Split on commas so `--only shellcheck,yamllint` works.
                      # The flag is documented as repeatable, but the comma form
                      # is what people reach for, and it used to silently run
                      # nothing.
                      IFS=',' read -r -a _only_parts <<< "$1"
                      ONLY+=("${_only_parts[@]}") ;;
        --list)       list_checks; exit 0 ;;
        --help|-h)    usage; exit 0 ;;
        --patches)     PATCHES=1 ;;
        --integration) INTEGRATION=1 ;;
        --smoke)       SMOKE=1 ;;
        *) echo "check.sh: unknown option '$1' (try --help)" >&2; exit 2 ;;
    esac
    shift
done

wanted() {
    [ ${#ONLY[@]} -eq 0 ] && return 0
    local n
    for n in "${ONLY[@]}"; do [ "$n" = "$1" ] && return 0; done
    return 1
}

# Reject an --only value that matches no check, rather than running nothing and
# calling it a pass. See the note on KNOWN_CHECKS above.
if [ ${#ONLY[@]} -gt 0 ]; then
    declare -a UNKNOWN=()
    for _n in "${ONLY[@]}"; do
        _found=0
        for _k in "${KNOWN_CHECKS[@]}"; do [ "$_n" = "$_k" ] && { _found=1; break; }; done
        [ "$_found" -eq 1 ] || UNKNOWN+=("$_n")
    done
    if [ ${#UNKNOWN[@]} -gt 0 ]; then
        for _n in "${UNKNOWN[@]}"; do
            echo "check.sh: no check matches '$_n' — see --list" >&2
        done
        exit 2
    fi
fi

have()       { command -v "$1" >/dev/null 2>&1; }
have_docker() { [ "$NO_DOCKER" -eq 0 ] && have docker && docker info >/dev/null 2>&1; }

# run_check <name> <description> — the body is the function check_<name>_run,
# which must print diagnostics to stdout and return non-zero on failure. Output
# is buffered so a passing check stays quiet and a failing one shows everything.
run_check() {
    local name="$1" desc="$2"
    wanted "$name" || return 0

    local out rc start elapsed
    out="$(mktemp)"
    start=$SECONDS
    # Only draw the in-progress line on a terminal. Redirected into a file or a
    # pipe there is no \r to erase it, so it would appear twice in every log.
    [ -t 1 ] && printf '  %-16s %s' "$name" "${C_DIM}${desc}${C_OFF}"

    "check_${name}_run" >"$out" 2>&1
    rc=$?
    elapsed=$(( SECONDS - start ))

    # \r returns to the column start and \033[K erases the in-progress text;
    # the format string is double-quoted so the colour variables expand.
    local eol=''
    [ -t 1 ] && eol=$'\033[K'
    case $rc in
        0)  printf "\r  %-16s ${C_GRN}PASS${C_OFF}  %-30s ${C_DIM}%ss${C_OFF}${eol}\n" "$name" "$desc" "$elapsed"
            PASSED+=("$name")
            [ "$VERBOSE" -eq 1 ] && sed 's/^/      /' "$out"
            ;;
        77) printf "\r  %-16s ${C_YEL}SKIP${C_OFF}  %-30s ${C_DIM}%s${C_OFF}${eol}\n" "$name" "$desc" "${SKIP_REASON[$name]:-unavailable}"
            SKIPPED+=("$name")
            ;;
        *)  printf "\r  %-16s ${C_RED}FAIL${C_OFF}  %-30s ${C_DIM}%ss${C_OFF}${eol}\n" "$name" "$desc" "$elapsed"
            FAILED+=("$name")
            sed 's/^/      /' "$out"
            ;;
    esac
    rm -f "$out"
}

skip() { SKIP_REASON["$1"]="$2"; return 77; }

# ─── shellcheck ─────────────────────────────────────────────────────
# Include list, not exclude list — matches .gitlab-ci.yml. Passing hdp.sh to
# find as a start path keeps this to a single invocation so the exit status
# cannot be masked.
check_shellcheck_run() {
    if have shellcheck; then
        find docker scripts hdp.sh -name '*.sh' -print0 \
            | xargs -0 -r shellcheck --severity=warning --
    elif have_docker; then
        docker run --rm -v "$REPO_ROOT":/mnt -w /mnt "$IMG_SHELLCHECK" \
            sh -c "find docker scripts hdp.sh -name '*.sh' -print0 | xargs -0 -r shellcheck --severity=warning --"
    else
        skip shellcheck "no shellcheck on PATH and no docker"
    fi
}

# ─── yamllint ───────────────────────────────────────────────────────
check_yamllint_run() {
    if have yamllint; then
        yamllint -d "$YAMLLINT_RULES" "${YAML_FILES[@]}"
    elif have_docker; then
        docker run --rm -v "$REPO_ROOT":/data -w /data "$IMG_YAMLLINT" \
            -d "$YAMLLINT_RULES" "${YAML_FILES[@]}"
    else
        skip yamllint "no yamllint on PATH and no docker"
    fi
}

# ─── ruff ───────────────────────────────────────────────────────────
check_ruff_run() {
    # tests/ is in scope as of Wave 2. It was not before, and `ruff check
    # docker/` would have skipped the entire suite silently — a linter that
    # does not see the tests is a linter that lets the tests rot.
    local args=(check docker/ scripts/ tests/)
    [ "$FIX" -eq 1 ] && args=(check --fix docker/ scripts/ tests/)

    if have ruff; then
        local v
        v="$(ruff --version 2>/dev/null | awk '{print $2}')"
        if [ -n "$v" ] && [ "$v" != "$RUFF_PINNED_VERSION" ]; then
            echo "note: host ruff $v differs from the pinned $RUFF_PINNED_VERSION;"
            echo "      rule sets change between releases, so CI may disagree."
            echo "      Re-run with --no-docker unset to use the pinned image."
        fi
        ruff "${args[@]}"
    elif have_docker; then
        docker run --rm -v "$REPO_ROOT":/io -w /io "$IMG_RUFF" "${args[@]}"
    else
        skip ruff "no ruff on PATH and no docker"
    fi
}

# ─── pytest, two tiers ──────────────────────────────────────────────
# Split because the cheap tier is cheap enough to run on every save and the
# other one is not. tests/unit imports nothing outside the standard library and
# finishes in about two seconds; tests/haystack needs haystack-ai and envsubst,
# which is ~47s to provision from a bare image and 0s if the project's haystack
# image is already built. scripts/ci/pytest.sh owns that decision.
#
# Both return 77 when they cannot run at all, which run_check renders as SKIP —
# and a skip is explicitly not a pass, per this script's contract.
check_pytest-unit_run() { scripts/ci/pytest.sh --tier unit; }

check_pytest-haystack_run() {
    [ -d tests/haystack ] || { skip pytest-haystack "tests/haystack does not exist yet"; return; }
    scripts/ci/pytest.sh --tier haystack
}

# ─── bats ───────────────────────────────────────────────────────────
# docker/infisical-loader.sh only. docker/setup.sh is not testable at this
# level — 557 lines, `set -euo pipefail`, `cd "$MW"` on line 18, and a
# top-to-bottom installer body — so asserting its exit code means standing up
# MariaDB and composer first. That is Wave 3's job.
check_bats_run() { scripts/ci/bats.sh; }

# ─── integration (opt-in) ───────────────────────────────────────────
# Wave 3's tier. Needs a running, installed wiki, so it is opt-in for the same
# reason `patches` is: check.sh's promise is a CI-equivalent verdict on a bare
# checkout with no .env and no containers, and a check that skips for everyone
# who has not booted seven containers is noise in that report.
#
# scripts/ci/pytest.sh probes for the stack and returns 77 when it is absent,
# which run_check renders as SKIP — never as a pass.
check_integration_run() {
    [ "$INTEGRATION" -eq 1 ] || { skip integration "opt-in: pass --integration (needs a running, installed wiki)"; return; }
    scripts/ci/pytest.sh --tier integration
}

# ─── smoke (opt-in) ─────────────────────────────────────────────────
# Wave 4's tier: the same directory as `integration`, run without the marker
# filter, so it is that check plus the legs needing mediawiki-jobrunner,
# haystack and chatbot-proxy. Opt-in for the same reason, one step further out —
# it needs all seven containers *and* a drained job queue, which is minutes of
# waiting rather than seconds.
#
# `scripts/ci/t4-smoke.sh` is the way to get a stack into that state from
# nothing; this check is for a stack you already have.
check_smoke_run() {
    [ "$SMOKE" -eq 1 ] || { skip smoke "opt-in: pass --smoke (needs the full 7-container stack)"; return; }
    scripts/ci/pytest.sh --tier smoke
}

# ─── php -l over the 17 settings.d files ────────────────────────────
# These gate ~130 extensions; a syntax error here takes the wiki down at boot.
check_php-lint_run() {
    if have php; then
        local f rc=0
        for f in app/settings.d/*.php; do php -l "$f" >/dev/null || rc=1; done
        [ $rc -eq 0 ] || { for f in app/settings.d/*.php; do php -l "$f" >/dev/null || php -l "$f"; done; return 1; }
    elif have_docker; then
        docker run --rm -v "$REPO_ROOT":/w -w /w "$IMG_PHP" \
            sh -c 'rc=0; for f in app/settings.d/*.php; do php -l "$f" >/dev/null || { php -l "$f"; rc=1; }; done; exit $rc'
    else
        skip php-lint "no php on PATH and no docker"
    fi
}

# ─── docker compose config ──────────────────────────────────────────
# v2 only. The `docker-compose` v1 binary parses a different schema, which is a
# reliable way to be green here and broken for everyone actually running v2.
#
# docker-compose.yml declares `env_file: .env`, so compose refuses to parse
# without one. A contributor who has not created a .env yet is exactly who this
# script is for, so materialise one from .env.example and remove it again.
# An existing .env is never touched.
# ─── T0 provenance ──────────────────────────────────────────────────
# Thin wrappers: all logic lives in scripts/ci/ so this script and the CI YAML
# stay thin callers of the same code and cannot drift apart.
check_gitleaks_run() {
    if ! command -v gitleaks >/dev/null 2>&1 && ! have_docker; then
        skip gitleaks "no gitleaks on PATH and no docker"; return
    fi
    scripts/ci/gitleaks.sh
}

check_env-example_run() { scripts/ci/env-example-check.sh; }

check_publiccode_run() {
    if ! command -v publiccode-parser >/dev/null 2>&1 && ! have_docker; then
        skip publiccode "no publiccode-parser and no docker"; return
    fi
    scripts/ci/publiccode-schema.sh
}

check_patch-ignore_run() { scripts/ci/patch-ignore-check.sh; }

# VERSIONS.yml against the tree: MW_VERSION, composer.lock, every
# extension.json, publiccode.yml, the compose image tags and the frozen-package
# strip list. ~1s, no containers, no network — and it is what makes "are we
# affected by CVE-X" answerable at all.
check_versions_run() { scripts/ci/version-consistency.sh; }

# Known CVEs in the 148 composer-visible packages, compared against the
# accepted baseline. The only check here that needs the network: it returns 77
# (SKIP) when the advisory database does not answer, because reporting "no new
# advisories" on a failed fetch is the one result a security gate must never
# give.
check_composer-audit_run() {
    if ! command -v composer >/dev/null 2>&1 && ! have_docker; then
        skip composer-audit "no composer on PATH and no docker"; return
    fi
    scripts/ci/composer-audit.sh
    local rc=$?
    [ "$rc" -eq 77 ] && skip composer-audit "the packagist advisory database did not answer"
    return $rc
}

# Manifest schema + target paths. Cheap (no patch(1), no composer), so it runs
# by default; the full verification needs a composer-installed tree and is
# opt-in via --patches.
check_manifest_run() { scripts/verify-patches.sh --static; }

# Full patch verification. Only meaningful after composer has run, because the
# Class-A markers are inserted by setup.sh post-install — in a fresh clone they
# are legitimately absent and this would report them missing.
check_patches_run() {
    [ "$PATCHES" -eq 1 ] || { skip patches "opt-in: pass --patches (needs a composer-installed tree)"; return; }
    scripts/verify-patches.sh
}

# ─── TF, the fresh-clone gate ───────────────────────────────────────
# Included in the default run because it is the highest-value check in the
# repo — six of the seven bugs in docs/QA-REPORT.md were only visible on a
# genuine fresh clone — and it costs ~15s with no Docker and no .env.
# It tests committed state, so it deliberately ignores your working tree.
check_fresh-clone_run() {
    [ -d .git ] || { skip fresh-clone "not a git checkout"; return; }
    scripts/ci/fresh-clone.sh
}

check_compose_run() {
    have_docker || { skip compose "docker not available"; return; }
    docker compose version >/dev/null 2>&1 || { skip compose "docker compose v2 not available"; return; }

    local made_env=0
    if [ ! -f .env ]; then
        [ -f .env.example ] || { echo "neither .env nor .env.example exists"; return 1; }
        cp .env.example .env
        made_env=1
    fi

    local rc=0
    docker compose config --quiet || rc=1
    [ "$made_env" -eq 1 ] && rm -f .env
    return $rc
}

# ─── Run ────────────────────────────────────────────────────────────
START=$SECONDS
echo ""
echo "${C_BLD}check.sh${C_OFF} — $(git rev-parse --short HEAD 2>/dev/null || echo 'no git') in $REPO_ROOT"
[ "$FIX" -eq 1 ] && echo "${C_YEL}--fix: auto-fixes will be written to your working tree${C_OFF}"
echo ""

run_check shellcheck "shell scripts we own"
run_check yamllint   "yaml we own"
run_check ruff       "python under docker/ scripts/ tests/"
run_check pytest-unit     "stdlib unit tests"
run_check pytest-haystack "to_native + load_pipeline"
run_check bats       "infisical-loader behaviour"
run_check php-lint   "app/settings.d syntax"
run_check compose    "compose interpolation"
run_check gitleaks     "no committed secrets"
run_check env-example  ".env.example completeness"
run_check publiccode   "publiccode.yml schema"
run_check patch-ignore "patch targets are trackable"
run_check manifest     "patch manifest schema"
run_check versions     "VERSIONS.yml matches the tree"
run_check composer-audit "no new CVEs in composer.lock"
run_check fresh-clone  "committed tree is complete"
run_check patches      "all 19 patches in the tree"
run_check integration  "live wiki serves real traffic"
run_check smoke        "full stack searches and answers"

TOTAL=$(( SECONDS - START ))

# ─── Summary ────────────────────────────────────────────────────────
echo ""
printf '  %s%d passed%s' "$C_GRN" "${#PASSED[@]}" "$C_OFF"
[ ${#FAILED[@]}  -gt 0 ] && printf ', %s%d failed%s'  "$C_RED" "${#FAILED[@]}"  "$C_OFF"
[ ${#SKIPPED[@]} -gt 0 ] && printf ', %s%d skipped%s' "$C_YEL" "${#SKIPPED[@]}" "$C_OFF"
printf '   %s(%ss)%s\n' "$C_DIM" "$TOTAL" "$C_OFF"

if [ ${#SKIPPED[@]} -gt 0 ]; then
    echo ""
    echo "  ${C_YEL}Skipped checks did not run and were not verified:${C_OFF}"
    for s in "${SKIPPED[@]}"; do
        printf '    %-16s %s\n' "$s" "${SKIP_REASON[$s]:-unavailable}"
    done
    echo "  Install the tool or start Docker to close these gaps; CI will run them regardless."
fi

if [ ${#FAILED[@]} -gt 0 ]; then
    echo ""
    echo "  ${C_RED}Failed:${C_OFF} ${FAILED[*]}"
    echo "  Re-run one at a time with:  ./scripts/check.sh --only <name>"
    [ "$FIX" -eq 0 ] && echo "  Some of these may be auto-fixable:  ./scripts/check.sh --fix"
    echo ""
    exit 1
fi

echo ""
if [ ${#SKIPPED[@]} -gt 0 ]; then
    # Do not claim parity with CI when some checks did not run. CI will run all
    # of them, so this result is weaker than a green pipeline, and saying
    # otherwise is how someone pushes a break they were told was fine.
    echo "  ${C_YEL}OK so far${C_OFF} — but ${#SKIPPED[@]} check(s) did not run, so this is NOT"
    echo "  the full CI verdict. CI will still run the skipped checks."
else
    echo "  ${C_GRN}OK${C_OFF} — this is the same verdict the CI lint stage will give."
fi
echo ""
