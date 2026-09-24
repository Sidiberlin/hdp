#!/bin/bash
# SPDX-License-Identifier: GPL-3.0-only
# Copyright (C) 2026 Sidiberlin
#
# ingest-scheduler.sh — periodic `ingest_hdp_wiki.py --missing-only` sidecar.
#
# Runs as the ingest-scheduler service's entrypoint (docker-compose.yml
# overrides `entrypoint:`, never `command:` — docker/haystack/Dockerfile has
# no CMD, so a `command:` override is appended as arguments to entrypoint.sh,
# which ignores them and boots the whole RAG stack instead of this loop).
#
# One rule buys the whole feature's safety (D2): this loop never performs the
# initial bulk ingest on its own. A cycle only runs `--missing-only` when
# OpenSearch answers AND hdp_wiki already holds at least one document —
# otherwise get_staged_revisions() in ingest_hdp_wiki.py returns {} and
# classify_pages() reads that as "index everything", turning a routine poll
# into an unattended full re-ingest the moment OpenSearch hiccups or the wiki
# is fresh. Until an operator runs the first ingestion by hand (or accepts
# install.sh's offer), this loop logs once per state change and does nothing
# else.
#
# `set -u`, deliberately no `set -e`: a failed cycle (a bad preflight, a
# crashed ingest_hdp_wiki.py) must not kill the container. `restart:
# unless-stopped` exists for the crash case, not for "OpenSearch hiccupped".
set -u

PIPELINE_DIR="/opt/pipeline"

# ─── Load secrets from Infisical ────────────────────────────────────
# entrypoint.sh does this for the haystack service. This container overrides
# entrypoint: instead of running entrypoint.sh, so without this every
# HDP_-prefixed secret (DB password, admin password, OpenSearch password, the
# ingestion bot password) would only ever come from the plaintext .env
# fallback, silently ignoring Infisical.
if [ -f /infisical-loader.sh ]; then
    source /infisical-loader.sh
else
    echo "WARNING: /infisical-loader.sh not found. Using .env values."
fi

# ─── Logging ──────────────────────────────────────────────────────────
# Matches ingest_hdp_wiki.py's own format (`%(asctime)s [%(levelname)s]
# %(message)s`), prefixed hdp-ingest-scheduler so `docker compose logs` tells
# the loop's own lines apart from the child process it runs (D5).
log() {  # log <LEVEL> <message>
    printf '%s [%s] hdp-ingest-scheduler: %s\n' \
        "$(date '+%Y-%m-%d %H:%M:%S,000')" "$1" "$2"
}

# ─── Pure decision functions ────────────────────────────────────────
# Kept free of I/O so tests/bats/ingest_scheduler.bats can source exactly
# these out of the live script (the same pattern tests/bats/gpu_wheel.bats
# uses on install.sh) instead of testing a copy that could drift. Everything
# below that decides what happens next calls only these — real curl/jq/
# python3 calls stay out of them.

# scheduler_disabled <interval_min> — true (status 0) when
# HDP_INGEST_INTERVAL_MIN=0, i.e. the scheduler should log once and sleep
# forever rather than loop (D6).
scheduler_disabled() {
    [ "$1" = "0" ]
}

# next_backoff_seconds <current_seconds> <ceiling_seconds> — doubles,
# capped at the ceiling (D5).
next_backoff_seconds() {
    local current="$1" ceiling="$2" next
    next=$(( current * 2 ))
    [ "$next" -gt "$ceiling" ] && next=$ceiling
    printf '%s' "$next"
}

# cycle_outcome <exit_code> — classifies ingest_hdp_wiki.py's exit code:
#   0  -> ok    (ran; "nothing to do" is also exit 0, see F1)
#   75 -> skip  (EX_TEMPFAIL — the D4 lock was held by another process)
#   *  -> fail  (a real failure; backs off)
cycle_outcome() {
    case "$1" in
        0)  printf 'ok' ;;
        75) printf 'skip' ;;
        *)  printf 'fail' ;;
    esac
}

# next_sleep_seconds <outcome> <current_seconds> <base_seconds> <ceiling_seconds>
# The one place D5's backoff policy is decided, for every caller:
#   ok      -> resets to the configured interval (a success ends any backoff)
#   skip    -> unchanged — a lock held by a concurrent ingestion is not a
#              failure of this loop and must not be punished with backoff
#              (D4: "no backoff, no alarm")
#   waiting -> the D2 preflight failed (OpenSearch down, or hdp_wiki is still
#              empty/missing) — no ingestion was attempted, and this backs
#              off exactly like a real failure
#   fail    -> anything else ingest_hdp_wiki.py exited with — backs off
next_sleep_seconds() {
    local outcome="$1" current="$2" base="$3" ceiling="$4"
    case "$outcome" in
        ok)   printf '%s' "$base" ;;
        skip) printf '%s' "$current" ;;
        *)    next_backoff_seconds "$current" "$ceiling" ;;
    esac
}

# ─── GPU sanity check ────────────────────────────────────────────────
# Mirrors docker/haystack/entrypoint.sh's check (see there for the incident
# it fixes). This service overrides entrypoint: rather than running
# entrypoint.sh, so without a copy of this check a GPU sidecar would embed
# silently on CPU with no signal anywhere that it happened (F5).
if [ "${HAYSTACK_DEVICE:-cpu}" = "gpu" ]; then
    echo "=== GPU check (HAYSTACK_DEVICE=gpu) ==="
    GPU_CHECK="$(python3 -c '
import torch
print("cuda_available=%s torch=%s cuda_build=%s" % (
    torch.cuda.is_available(), torch.__version__, torch.version.cuda))
if torch.cuda.is_available():
    print("device=%s" % torch.cuda.get_device_name(0))
' 2>&1)"
    echo "$GPU_CHECK"
    if ! printf '%s' "$GPU_CHECK" | grep -q 'cuda_available=True'; then
        echo ""
        echo "FATAL: HAYSTACK_DEVICE=gpu but torch.cuda.is_available() is False."
        echo "       This container would silently ingest every embedding on CPU."
        echo "       See docker/haystack/entrypoint.sh's identical check and"
        echo "       README-DOCKER.md#gpu-inference for the common causes."
        exit 1
    fi
    echo "GPU check passed."
fi

# ─── Configuration ──────────────────────────────────────────────────
INTERVAL_MIN="${HDP_INGEST_INTERVAL_MIN:-5}"
MAX_PAGES="${HDP_INGEST_MAX_PAGES:-25}"
BACKOFF_MAX_MIN="${HDP_INGEST_BACKOFF_MAX_MIN:-60}"

if scheduler_disabled "$INTERVAL_MIN"; then
    log INFO "HDP_INGEST_INTERVAL_MIN=0 — scheduler disabled; staying up idle"
    sleep infinity
    exit 0
fi

BASE_SLEEP_SECONDS=$(( INTERVAL_MIN * 60 ))
CEILING_SECONDS=$(( BACKOFF_MAX_MIN * 60 ))

OPENSEARCH_HOST="${OPENSEARCH_HOST:-opensearch}"
OPENSEARCH_PORT="${OPENSEARCH_PORT:-9200}"
OPENSEARCH_URL="https://${OPENSEARCH_HOST}:${OPENSEARCH_PORT}"
OS_AUTH="admin:${OPENSEARCH_PASSWORD:-admin}"  # gitleaks:allow — see entrypoint.sh's identical line

# ─── D2 preflight ────────────────────────────────────────────────────
# OpenSearch up AND hdp_wiki has at least one document. Anything else and
# get_staged_revisions() would return {} and turn --missing-only into a full
# re-ingest (F2) — exactly what this loop must never do on its own.
#
# Not unit-tested directly (it is real curl/jq I/O) — the same call
# tests/bats/ingest_scheduler.bats makes about the D4 flock: the pure
# next_sleep_seconds()/cycle_outcome() functions above are what carry the
# policy, and those are pinned.
preflight_ok() {
    curl -sk -u "$OS_AUTH" "${OPENSEARCH_URL}/_cluster/health" >/dev/null 2>&1 || return 1
    local count
    count="$(curl -sk -u "$OS_AUTH" "${OPENSEARCH_URL}/hdp_wiki/_count" 2>/dev/null \
        | jq -r '.count // 0' 2>/dev/null)"
    case "$count" in ''|*[!0-9]*) return 1 ;; esac
    [ "$count" -gt 0 ]
}

# The actual child process, wrapped in its own function only so a future test
# could override it without touching PATH; the real loop always calls this.
run_ingest_cycle() {
    python3 "$PIPELINE_DIR/ingest_hdp_wiki.py" --missing-only --max-pages "$MAX_PAGES"
}

log INFO "starting — interval=${INTERVAL_MIN}m max-pages=${MAX_PAGES} backoff-max=${BACKOFF_MAX_MIN}m"

sleep_seconds="$BASE_SLEEP_SECONDS"
last_state=""

while :; do
    if preflight_ok; then
        [ "$last_state" = "ready" ] || log INFO "OpenSearch is up and hdp_wiki has documents — ingest cycles will run"
        last_state="ready"

        run_ingest_cycle
        rc=$?
        outcome="$(cycle_outcome "$rc")"
        case "$outcome" in
            skip) log INFO "ingestion lock held by another process — skipping this cycle (D4)" ;;
            fail) log WARNING "ingest_hdp_wiki.py exited $rc — backing off" ;;
        esac
    else
        [ "$last_state" = "waiting" ] || log INFO "waiting for the initial ingestion — OpenSearch is unreachable or hdp_wiki is empty/missing; run it by hand once (see README-DOCKER.md)"
        last_state="waiting"
        outcome="waiting"
    fi

    sleep_seconds="$(next_sleep_seconds "$outcome" "$sleep_seconds" "$BASE_SLEEP_SECONDS" "$CEILING_SECONDS")"
    sleep "$sleep_seconds"
done
