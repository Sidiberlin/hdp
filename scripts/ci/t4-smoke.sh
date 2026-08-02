#!/usr/bin/env bash
# ============================================================
# T4 — the full-stack smoke job, end to end.
#
#   fresh volumes -> build -> compose up (all 7) -> setup.sh -> wait healthy
#     -> drain the job queue -> ingest the wiki -> tests/integration (unfiltered)
#     -> teardown
#
# One script for both CI and a developer, the same contract
# scripts/ci/t3-integration.sh and scripts/check.sh hold: the thing that runs
# in the pipeline is the thing anyone can run in a terminal, and there is no
# CI-only YAML holding logic nobody can reproduce.
#
#   scripts/ci/t4-smoke.sh                the whole sequence
#   scripts/ci/t4-smoke.sh --keep         leave the stack up afterwards
#   scripts/ci/t4-smoke.sh --no-build     reuse images already built
#   scripts/ci/t4-smoke.sh --no-ingest    skip the ~8 minute reindex
#   scripts/ci/t4-smoke.sh --cache        add the buildx GHA layer cache
#
# ─── How this differs from T3, and why T4 is a separate job ─────────
#
# T3 runs four containers and skips the two expensive ones. That is the right
# trade for a per-push gate — haystack alone is a 2.5 GB image and ~285 of the
# ~290 seconds a full build takes — but it leaves two legs of this system
# completely untested, and Wave 3 said so explicitly:
#
#   * Search. BlueSpice's ExtendedSearch does not index synchronously.
#     setup.sh runs initBackends.php, which creates the index and *enqueues*
#     one job per page; mediawiki-jobrunner is what actually fills it. T3 has
#     no jobrunner, so its wiki sits on ~500 pending jobs with an empty index,
#     and every full-text query returns nothing while the configuration is
#     perfectly correct.
#   * The chatbot. haystack and chatbot-proxy are not in T3's profile at all,
#     so /health, /ready, /session and /chat-stream were asserted nowhere —
#     including the /chat-stream status code that Wave 0 Issue 2 was about.
#
# So T4 runs the full seven and is nightly plus tags rather than per push. It
# is a *superset*: the tests are the same directory, run without the marker
# filter, so everything T3 asserts is asserted here too and there is no second
# suite to keep in step.
#
# ─── The two waits, and why they are here rather than in the tests ──
#
# Draining the job queue and ingesting the wiki take minutes and are *setup*,
# not assertions. They live here so a failure reads as "the stack could not be
# brought to the state under test" rather than as a test that timed out. The
# assertions about the resulting state — queue empty, index populated, 153
# documents — are in tests/integration/test_search.py, which is where a human
# looks to find out what broke.
#
# Ingestion is skippable (--no-ingest) because it is the single most expensive
# step (~8 minutes; it embeds 155 sections on CPU) and a developer iterating on
# the chatbot leg does not need it. The tests know the difference: HDP_INGEST_RAN
# tells test_search.py whether to assert the exact count or merely a populated
# index, so skipping degrades the assertion honestly instead of silently.
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT" || exit 1

# shellcheck source=scripts/ci/lib/stack.sh
. "$REPO_ROOT/scripts/ci/lib/stack.sh"

# All seven. Note that `pdf-generator`, which the Wave 4 brief lists, is not a
# service: it is the haystack container's second port (HDP_PDF_PORT, 1417).
# The seventh container is mediawiki-web. See FULL_STACK_SERVICES in
# tests/integration/conftest.py.
SERVICES="${HDP_T4_SERVICES:-mariadb opensearch mediawiki mediawiki-web mediawiki-jobrunner haystack chatbot-proxy}"

KEEP=0
BUILD=1
INGEST=1
CACHE=0
WIKI_URL="${HDP_WIKI_URL:-http://localhost:8080/w}"

# The compose overlay that adds buildx GHA cache settings and redirects the
# HuggingFace model cache to a bind mount the CI cache action can save. Never
# part of the default stack: it belongs to the runner, not to the product.
CACHE_OVERLAY="docker/ci/compose.cache.yml"

# How long to wait for all seven to report healthy after setup.sh. haystack has
# a 90s start_period of its own and downloads ~1.7 GB of model weights on a
# cold cache before it answers at all.
HEALTH_BUDGET="${HDP_T4_HEALTH_TIMEOUT:-900}"

# How long to let mediawiki-jobrunner work the queue down. Passed through to
# the tests as well, so the wait here and the assertion there agree.
JOBQUEUE_BUDGET="${HDP_JOBQUEUE_TIMEOUT:-900}"

while [ $# -gt 0 ]; do
    case "$1" in
        --keep)       KEEP=1 ;;
        --no-build)   BUILD=0 ;;
        --no-ingest)  INGEST=0 ;;
        --cache)      CACHE=1 ;;
        --services)   shift; [ $# -gt 0 ] || { echo "--services needs a value" >&2; exit 2; }; SERVICES="$1" ;;
        -h|--help)    sed -n '2,30p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "t4-smoke.sh: unknown option '$1' (try --help)" >&2; exit 2 ;;
    esac
    shift
done

# shellcheck disable=SC2206  # SERVICES is a deliberate word-split list
read -r -a SERVICE_LIST <<< "$SERVICES"

fail() { printf '\033[0;31mT4 FAILED: %s\033[0m\n' "$*" >&2; }

command -v docker >/dev/null 2>&1 || { fail "no docker on PATH"; exit 2; }
docker compose version >/dev/null 2>&1 || { fail "docker compose v2 is required"; exit 2; }

COMPOSE_FILES=(-f docker-compose.yml)
if [ "$CACHE" -eq 1 ]; then
    [ -f "$CACHE_OVERLAY" ] || { fail "--cache needs $CACHE_OVERLAY"; exit 2; }
    COMPOSE_FILES+=(-f "$CACHE_OVERLAY")
    # Compose delegates the build to buildx bake, which is what reads the
    # `x-bake` block in the overlay. Without this the cache settings are
    # silently ignored and the build looks fine while caching nothing.
    export COMPOSE_BAKE=1
    mkdir -p "$REPO_ROOT/.hf-cache"
fi

dc() { docker compose "${COMPOSE_FILES[@]}" "$@"; }

SETUP_LOG="$(mktemp -t hdp-setup-XXXXXX.log)"
CREATED_ENV=0

cleanup() {
    local rc=$?
    if [ "$KEEP" -eq 1 ]; then
        hdp_say "leaving the stack up (--keep). Tear it down with: docker compose down -v"
        # The generated .env stays with it. Removing it would leave a stack
        # whose passwords are unrecoverable — nothing could log in, and even
        # `docker compose down` would refuse to parse, since compose declares
        # `env_file: .env`.
        [ "$CREATED_ENV" -eq 1 ] && hdp_say "kept the generated .env — it holds this stack's passwords"
    else
        hdp_say "tearing down"
        # -v so the next run starts from empty volumes. A populated
        # mariadb_data makes setup.sh skip install.php, and then this job
        # silently stops testing the install it exists to test — and a
        # populated opensearch_data makes the search leg assert against an
        # index somebody else's run filled.
        dc down -v --remove-orphans >/dev/null 2>&1
        [ "$CREATED_ENV" -eq 1 ] && rm -f "$REPO_ROOT/.env"
    fi
    rm -f "$SETUP_LOG"
    exit "$rc"
}
trap cleanup EXIT INT TERM

# ─── .env ───────────────────────────────────────────────────────────
hdp_say "preparing .env"
ENV_STATE="$(hdp_generate_env "$REPO_ROOT")" || { fail "could not prepare .env"; exit 2; }
[ "$ENV_STATE" = "generated" ] && CREATED_ENV=1
echo "  .env: $ENV_STATE"

# HDP_LLM_API_KEY is deliberately NOT generated. Without one the chatbot leg
# asserts the degraded contract (/ready 503, /chat-stream 503), which is the
# contract CI must hold; with one — exported by whoever runs this on a box —
# it asserts the streaming contract instead. Both live in test_chatbot.py.
if [ -n "${HDP_LLM_API_KEY:-}" ]; then
    echo "  HDP_LLM_API_KEY is set in the environment: the end-to-end chat leg will run"
else
    echo "  no HDP_LLM_API_KEY: the chatbot leg will assert the degraded contract"
fi

# ─── boot ───────────────────────────────────────────────────────────
hdp_say "starting the full stack: ${SERVICE_LIST[*]}"
if [ "$BUILD" -eq 1 ]; then
    BUILD_START=$SECONDS
    if ! dc build "${SERVICE_LIST[@]}"; then
        fail "docker compose build"
        exit 1
    fi
    echo "build seconds: $(( SECONDS - BUILD_START ))"
fi
if ! dc up -d "${SERVICE_LIST[@]}"; then
    fail "docker compose up"
    dc ps
    exit 1
fi

hdp_say "waiting for the web container to accept connections"
if ! hdp_wait_for_web "$WIKI_URL" 300; then
    fail "nothing answering at $WIKI_URL/ after 300s"
    dc ps
    dc logs --no-color --tail 60 mediawiki-web mediawiki
    exit 1
fi

# ─── install ────────────────────────────────────────────────────────
hdp_say "running setup.sh"
dc exec -T mediawiki bash /setup.sh 2>&1 | tee "$SETUP_LOG"
SETUP_EXIT="${PIPESTATUS[0]}"
echo "setup.sh exit: $SETUP_EXIT"

# Not an early exit. setup.sh is warn-then-fail-at-end by design: it finishes
# the install and reports at the end, so a non-zero exit still leaves a wiki
# worth running the assertions against — and those assertions are what say
# *which* part is broken. test_install_update.py asserts the exit code itself,
# so a failure here is still a failed job.
if [ "$SETUP_EXIT" -ne 0 ]; then
    hdp_say "setup.sh exited $SETUP_EXIT — continuing, so the tests can say what broke"
fi

# ─── wait for all seven ─────────────────────────────────────────────
# Only meaningful *after* setup.sh: before it there is no LocalSettings.php,
# so mediawiki-web is legitimately unhealthy. This is a wait, not a check —
# test_full_stack.py makes the assertion, and does it against the same
# `docker compose ps` output.
hdp_say "waiting for all ${#SERVICE_LIST[@]} containers to report healthy (up to ${HEALTH_BUDGET}s)"
#
# `ps --format json` is parsed rather than a Go template: compose emits one
# JSON object per line (an array in some older v2 patch releases) and a
# template with an empty Health field silently shifts the columns, so an
# unhealthy container reads as a healthy one.
not_healthy() {
    dc ps --all --format json 2>/dev/null | python3 -c '
import json, sys
bad = []
for chunk in sys.stdin.read().strip().splitlines():
    chunk = chunk.strip()
    if not chunk:
        continue
    entries = json.loads(chunk)
    if isinstance(entries, dict):
        entries = [entries]
    for e in entries:
        if e.get("State") != "running" or e.get("Health") != "healthy":
            bad.append(f"{e.get(\"Service\")}({e.get(\"State\")}/{e.get(\"Health\") or \"no-health\"})")
print(" ".join(bad))
'
}

health_deadline=$(( SECONDS + HEALTH_BUDGET ))
while :; do
    unhealthy="$(not_healthy)"
    [ -z "${unhealthy// /}" ] && break
    if [ "$SECONDS" -ge "$health_deadline" ]; then
        hdp_say "still not healthy after ${HEALTH_BUDGET}s: $unhealthy"
        break
    fi
    echo "  waiting on: $unhealthy"
    sleep 10
done
dc ps

# ─── drain the job queue ────────────────────────────────────────────
# The search leg is entirely downstream of this. See the header.
hdp_say "waiting for mediawiki-jobrunner to drain the queue (up to ${JOBQUEUE_BUDGET}s)"
job_deadline=$(( SECONDS + JOBQUEUE_BUDGET ))
while :; do
    # The last numeric line, not the whole output: run.php prints a banner on
    # some builds, and collapsing every line into one string would glue the
    # banner onto the count.
    pending="$(dc exec -T mediawiki php maintenance/run.php showJobs.php 2>/dev/null \
        | tr -d '\r' | grep -E '^[0-9]+$' | tail -1)"
    case "$pending" in
        ''|*[!0-9]*) echo "  showJobs.php gave ${pending:-no output}; retrying"; pending=-1 ;;
        *) echo "  pending jobs: $pending" ;;
    esac
    [ "$pending" = "0" ] && break
    if [ "$SECONDS" -ge "$job_deadline" ]; then
        hdp_say "queue still at ${pending} after ${JOBQUEUE_BUDGET}s — letting the tests report it"
        break
    fi
    sleep 15
done

# ─── ingest ─────────────────────────────────────────────────────────
INGEST_RAN=0
if [ "$INGEST" -eq 1 ]; then
    hdp_say "ingesting the wiki into the hdp_wiki index (this takes several minutes)"
    ingest_start=$SECONDS
    if dc exec -T haystack python3 /opt/pipeline/ingest_hdp_wiki.py; then
        INGEST_RAN=1
        echo "ingest seconds: $(( SECONDS - ingest_start ))"
    else
        # Not fatal here: test_search.py asserts the resulting document count
        # and will say what the index actually holds, which is more useful than
        # this script's exit code.
        hdp_say "ingest_hdp_wiki.py failed after $(( SECONDS - ingest_start ))s — letting the tests report it"
    fi
else
    hdp_say "skipping ingestion (--no-ingest); the hdp_wiki assertion degrades to 'populated'"
fi

# ─── assert ─────────────────────────────────────────────────────────
hdp_say "running the smoke tier (the integration directory, unfiltered)"
HDP_SETUP_EXIT="$SETUP_EXIT" \
HDP_SETUP_LOG="$SETUP_LOG" \
HDP_WIKI_URL="$WIKI_URL" \
HDP_INGEST_RAN="$INGEST_RAN" \
HDP_JOBQUEUE_TIMEOUT="$JOBQUEUE_BUDGET" \
    scripts/ci/pytest.sh --tier smoke
TEST_EXIT=$?

# 77 means the tier could not run at all. This job's entire purpose is to make
# it runnable, so here — unlike in check.sh — that is a failure, not a skip.
if [ "$TEST_EXIT" -eq 77 ]; then
    fail "the smoke tier could not run, after this job started a stack for it"
    dc ps
    exit 1
fi

if [ "$TEST_EXIT" -ne 0 ]; then
    fail "smoke tests"
    exit 1
fi

hdp_say "T4 PASSED"
exit 0
