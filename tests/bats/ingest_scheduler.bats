#!/usr/bin/env bats
# Pin: the pure decision functions inside docker/haystack/ingest-scheduler.sh
# — backoff doubling and its ceiling (D5), the interval=0 disabled branch
# (D6), and the exit-code classification the D4 lock and D2 preflight are
# built on. No docker, no network, no live loop.
#
# These tests exercise the real function text, not a copy that could drift —
# the same load_functions() pattern tests/bats/gpu_wheel.bats uses on
# install.sh: awk slices scheduler_disabled(), next_backoff_seconds(),
# cycle_outcome() and next_sleep_seconds() straight out of
# ingest-scheduler.sh and sources only that slice. None of the script's
# top-level side-effecting code (Infisical loading, the GPU check, the
# curl-based preflight, the main loop) runs.
#
# The D4 flock itself is deliberately NOT tested here — see the plan's test
# section: a two-process flock test is flaky in CI containers. What is
# pinned is the loop's *response* to the lock being held: cycle_outcome(75)
# must classify as "skip", and next_sleep_seconds("skip", ...) must leave
# the sleep interval unchanged (D4: "no backoff, no alarm").

load_functions() {
    SCHEDULER_SH="$BATS_TEST_DIRNAME/../../docker/haystack/ingest-scheduler.sh"
    [ -f "$SCHEDULER_SH" ] || { echo "ingest-scheduler.sh not found at $SCHEDULER_SH" >&2; return 1; }
    FUNCTIONS_LIB="$BATS_TEST_TMPDIR/scheduler_functions.sh"
    awk '
        /^scheduler_disabled\(\)/ || /^next_backoff_seconds\(\)/ || /^cycle_outcome\(\)/ || /^next_sleep_seconds\(\)/ { grab = 1 }
        grab { print }
        grab && /^}/ { grab = 0 }
    ' "$SCHEDULER_SH" > "$FUNCTIONS_LIB"
    # shellcheck disable=SC1090
    source "$FUNCTIONS_LIB"
}

setup() {
    load_functions
}

# ─── scheduler_disabled (D6) ──────────────────────────────────────────

@test "scheduler_disabled: interval 0 is disabled" {
    run scheduler_disabled "0"
    [ "$status" -eq 0 ]
}

@test "scheduler_disabled: interval 5 (the default) is NOT disabled" {
    run scheduler_disabled "5"
    [ "$status" -ne 0 ]
}

@test "scheduler_disabled: interval 1 is NOT disabled" {
    run scheduler_disabled "1"
    [ "$status" -ne 0 ]
}

# ─── next_backoff_seconds: doubling and its ceiling (D5) ──────────────

@test "next_backoff_seconds: doubles a mid-range value" {
    run next_backoff_seconds "300" "3600"
    [ "$status" -eq 0 ]
    [ "$output" = "600" ]
}

@test "next_backoff_seconds: doubling repeatedly climbs toward the ceiling" {
    run next_backoff_seconds "300" "3600"
    [ "$output" = "600" ]
    run next_backoff_seconds "600" "3600"
    [ "$output" = "1200" ]
    run next_backoff_seconds "1200" "3600"
    [ "$output" = "2400" ]
}

@test "next_backoff_seconds: caps at the ceiling instead of overshooting" {
    run next_backoff_seconds "2400" "3600"
    [ "$status" -eq 0 ]
    [ "$output" = "3600" ]
}

@test "next_backoff_seconds: already at the ceiling stays at the ceiling" {
    run next_backoff_seconds "3600" "3600"
    [ "$output" = "3600" ]
}

@test "next_backoff_seconds: default interval (300s=5min) doubling toward default ceiling (3600s=60min)" {
    # Mirrors the documented defaults: HDP_INGEST_INTERVAL_MIN=5,
    # HDP_INGEST_BACKOFF_MAX_MIN=60.
    run next_backoff_seconds "300" "3600"
    [ "$output" = "600" ]
}

# ─── cycle_outcome: exit-code classification (D4) ──────────────────────

@test "cycle_outcome: exit 0 is ok" {
    run cycle_outcome "0"
    [ "$output" = "ok" ]
}

@test "cycle_outcome: exit 75 (EX_TEMPFAIL, the D4 lock held) is skip" {
    run cycle_outcome "75"
    [ "$output" = "skip" ]
}

@test "cycle_outcome: any other exit code is fail" {
    for rc in 1 2 74 76 137; do
        run cycle_outcome "$rc"
        [ "$output" = "fail" ]
    done
}

# ─── next_sleep_seconds: the full D5 policy, per outcome ───────────────

@test "next_sleep_seconds: ok resets to the base interval, even mid-backoff" {
    run next_sleep_seconds "ok" "2400" "300" "3600"
    [ "$output" = "300" ]
}

@test "next_sleep_seconds: skip (lock held) leaves the interval unchanged — no backoff, no alarm (D4)" {
    run next_sleep_seconds "skip" "300" "300" "3600"
    [ "$output" = "300" ]

    # And critically: it does not reset to base OR double — it is a true no-op,
    # even when a previous failure had already pushed the sleep interval up.
    run next_sleep_seconds "skip" "1200" "300" "3600"
    [ "$output" = "1200" ]
}

@test "next_sleep_seconds: fail doubles like a real failure" {
    run next_sleep_seconds "fail" "300" "300" "3600"
    [ "$output" = "600" ]
}

@test "next_sleep_seconds: waiting (a failed D2 preflight) backs off exactly like fail — this is the 'preflight failure -> skip-with-backoff' case" {
    run next_sleep_seconds "waiting" "300" "300" "3600"
    [ "$output" = "600" ]
    run next_sleep_seconds "waiting" "2400" "300" "3600"
    [ "$output" = "3600" ]
}

@test "next_sleep_seconds: fail respects the ceiling" {
    run next_sleep_seconds "fail" "3600" "300" "3600"
    [ "$output" = "3600" ]
}
