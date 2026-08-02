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

# Host binaries are a convenience, not the source of truth. When a host tool's
# version differs from the pinned image the verdicts can differ too, so say so
# instead of letting someone chase a discrepancy CI will not reproduce.
RUFF_PINNED_VERSION="0.16.1"

YAMLLINT_RULES='{extends: default, rules: {line-length: disable, document-start: disable, truthy: disable}}'
YAML_FILES=(docker-compose.yml publiccode.yml docker/haystack/hdp_pipeline.yaml .gitlab-ci.yml)

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
VERBOSE=0
NO_DOCKER=0
declare -a ONLY=()

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

No .env, no running stack, and no toolchain install required. Checks that can
run neither from PATH nor from a pinned image are reported SKIPPED, never
passed.
EOF
}

list_checks() {
    printf '%-14s %-34s %s\n' CHECK WHAT REQUIRES
    printf '%-14s %-34s %s\n' ----- ---- --------
    printf '%-14s %-34s %s\n' shellcheck  'shell in docker/ scripts/ hdp.sh' "shellcheck | $IMG_SHELLCHECK"
    printf '%-14s %-34s %s\n' yamllint    'compose, publiccode, pipeline, CI' "yamllint | $IMG_YAMLLINT"
    printf '%-14s %-34s %s\n' ruff        'python under docker/' "ruff | $IMG_RUFF"
    printf '%-14s %-34s %s\n' php-lint    'syntax of app/settings.d/*.php' "php | $IMG_PHP"
    printf '%-14s %-34s %s\n' compose     'docker-compose.yml interpolates' 'docker compose v2'
    printf '%-14s %-34s %s\n' gitleaks    'no secrets in owned paths' 'gitleaks | zricethezav/gitleaks'
    printf '%-14s %-34s %s\n' env-example '.env.example covers compose' 'grep'
    printf '%-14s %-34s %s\n' publiccode  'publiccode.yml schema' 'italia/publiccode-parser-go'
    printf '%-14s %-34s %s\n' patch-ignore 'no patch target is gitignored' 'git'
    printf '%-14s %-34s %s\n' manifest    'patch manifest schema' 'python3 + pyyaml'
    printf '%-14s %-34s %s\n' fresh-clone 'TF: fresh clone has every input' 'git'
    printf '%-14s %-34s %s\n' patches     'all 19 patches present (--patches)' 'patch(1)'
}

while [ $# -gt 0 ]; do
    case "$1" in
        --fix)        FIX=1 ;;
        --verbose|-v) VERBOSE=1 ;;
        --no-docker)  NO_DOCKER=1 ;;
        --only)       shift; [ $# -gt 0 ] || { echo "--only needs a name" >&2; exit 2; }; ONLY+=("$1") ;;
        --list)       list_checks; exit 0 ;;
        --help|-h)    usage; exit 0 ;;
        --patches)    PATCHES=1 ;;
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
    [ -t 1 ] && printf '  %-12s %s' "$name" "${C_DIM}${desc}${C_OFF}"

    "check_${name}_run" >"$out" 2>&1
    rc=$?
    elapsed=$(( SECONDS - start ))

    # \r returns to the column start and \033[K erases the in-progress text;
    # the format string is double-quoted so the colour variables expand.
    local eol=''
    [ -t 1 ] && eol=$'\033[K'
    case $rc in
        0)  printf "\r  %-12s ${C_GRN}PASS${C_OFF}  %-30s ${C_DIM}%ss${C_OFF}${eol}\n" "$name" "$desc" "$elapsed"
            PASSED+=("$name")
            [ "$VERBOSE" -eq 1 ] && sed 's/^/      /' "$out"
            ;;
        77) printf "\r  %-12s ${C_YEL}SKIP${C_OFF}  %-30s ${C_DIM}%s${C_OFF}${eol}\n" "$name" "$desc" "${SKIP_REASON[$name]:-unavailable}"
            SKIPPED+=("$name")
            ;;
        *)  printf "\r  %-12s ${C_RED}FAIL${C_OFF}  %-30s ${C_DIM}%ss${C_OFF}${eol}\n" "$name" "$desc" "$elapsed"
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
    local args=(check docker/)
    [ "$FIX" -eq 1 ] && args=(check --fix docker/)

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
run_check ruff       "python under docker/"
run_check php-lint   "app/settings.d syntax"
run_check compose    "compose interpolation"
run_check gitleaks     "no committed secrets"
run_check env-example  ".env.example completeness"
run_check publiccode   "publiccode.yml schema"
run_check patch-ignore "patch targets are trackable"
run_check manifest     "patch manifest schema"
run_check fresh-clone  "committed tree is complete"
run_check patches      "all 19 patches in the tree"

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
        printf '    %-12s %s\n' "$s" "${SKIP_REASON[$s]:-unavailable}"
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
