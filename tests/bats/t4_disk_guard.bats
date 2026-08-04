#!/usr/bin/env bats
# ============================================================
# The T4 free-space precondition — hdp_disk_required_kb, in
# scripts/ci/lib/stack.sh.
#
# This exists because the first version of the guard was a flat 14 GB floor
# that passed a runner which then failed. The whole check is four lines of
# integer arithmetic guarding a 90-minute job, and it is only worth anything if
# it gets the two runs we actually have right:
#
#   run 30873842113  34 GB free on a 72 GB runner   must pass
#   run 30882658336  15 GB free on a 72 GB runner   must fail
#
# The second one failed with `disk usage exceeded flood-stage watermark, index
# has read-only-allow-delete block` — 67 minutes in, having reported success at
# every earlier step. That is the failure these numbers are chosen to make
# impossible, so they are asserted here rather than left as a comment.
#
# Pure arithmetic: no docker, no df, no stack. Sourcing stack.sh is safe
# because it defines functions and does nothing else.
# ============================================================

setup() {
    REPO_ROOT="$(cd "${BATS_TEST_DIRNAME}/../.." && pwd)"
    # shellcheck source=scripts/ci/lib/stack.sh
    . "$REPO_ROOT/scripts/ci/lib/stack.sh"

    GB=$(( 1024 * 1024 ))          # KB in a GB
    RUNNER_KB=$(( 72 * GB ))       # the ubuntu-latest filesystem, as measured
}

# ─── the two data points ────────────────────────────────────────────

@test "15 GB free on the 72 GB runner is refused (run 30882658336)" {
    need="$(hdp_disk_required_kb "$RUNNER_KB")"
    free=$(( 15 * GB ))
    [ "$free" -lt "$need" ]
}

@test "35 GB free on the 72 GB runner is accepted (run 30873842113)" {
    need="$(hdp_disk_required_kb "$RUNNER_KB")"
    free=$(( 35 * GB ))
    [ "$free" -ge "$need" ]
}

# 34 is what the passing run actually printed; 35 is the round number the
# requirement was reviewed against. Both must pass, and the gap between them is
# not where the boundary is allowed to sit.
@test "34 GB free on the 72 GB runner is accepted (what the run printed)" {
    need="$(hdp_disk_required_kb "$RUNNER_KB")"
    free=$(( 34 * GB ))
    [ "$free" -ge "$need" ]
}

# ─── which term is doing the work ───────────────────────────────────

@test "on the 72 GB runner the 20 GB floor is what fires" {
    # stack 12 + 5% of 72 = 15.6, so the floor is the binding constraint here.
    # Asserted explicitly: if someone later drops the floor believing the
    # percentage covers it, this is the test that says it does not.
    [ "$(hdp_disk_required_kb "$RUNNER_KB")" -eq $(( 20 * GB )) ]
}

@test "the old flat 14 GB floor would have let the failing run through" {
    # The regression, stated as a test. 15 GB cleared 14 GB and still died.
    [ $(( 15 * GB )) -ge $(( 14 * GB )) ]
    [ $(( 15 * GB )) -lt "$(hdp_disk_required_kb "$RUNNER_KB")" ]
}

@test "on a large filesystem the watermark term overtakes the floor" {
    # 5% of 400 GB is 20 GB, so the requirement is 12 + 20 = 32 GB and a flat
    # floor would be the thing that is wrong. This is the case the percentage
    # exists for.
    [ "$(hdp_disk_required_kb $(( 400 * GB )))" -eq $(( 32 * GB )) ]
}

@test "requirement never drops below the floor on a small filesystem" {
    [ "$(hdp_disk_required_kb $(( 20 * GB )))" -eq $(( 20 * GB )) ]
}

@test "requirement rises monotonically with filesystem size" {
    prev=0
    for size in 20 72 160 400 1000; do
        cur="$(hdp_disk_required_kb $(( size * GB )))"
        [ "$cur" -ge "$prev" ]
        prev="$cur"
    done
}

@test "a 1 TB filesystem does not overflow the arithmetic" {
    # total_kb * 5 would be 5.4e9 and wrap a 32-bit shell; the function divides
    # first for exactly this reason. 12 + 5% of 1024 = 63.2 GB.
    got="$(hdp_disk_required_kb $(( 1024 * GB )))"
    [ "$got" -gt 0 ]
    [ "$got" -eq $(( 12 * GB + 1024 * GB / 100 * 5 )) ]
}

# ─── the override, which CI and developers both rely on ─────────────

@test "the stack-need and floor are overridable for other hardware" {
    [ "$(hdp_disk_required_kb "$RUNNER_KB" 30 0)" -eq $(( 30 * GB + RUNNER_KB / 100 * 5 )) ]
}

# ─── the message the guard prints ───────────────────────────────────

@test "hdp_gb keeps the tenths that the whole derivation turns on" {
    # 15.6 vs 15 is the entire difference between the guard working and not.
    [ "$(hdp_gb $(( RUNNER_KB / 100 * 5 + 12 * GB )))" = "15.6" ]
    [ "$(hdp_gb $(( 20 * GB )))" = "20.0" ]
    [ "$(hdp_gb "$RUNNER_KB")" = "72.0" ]
    [ "$(hdp_gb $(( 15 * GB )))" = "15.0" ]
}

@test "hdp_gb rounds a requirement up rather than under-reporting it" {
    # Truncation here would print 15.5 for a 15.6 threshold and send somebody
    # off to free the wrong amount.
    [ "$(hdp_gb $(( 15 * GB + GB * 58 / 100 )))" = "15.6" ]
    # …and carries instead of printing a tenth of 10.
    [ "$(hdp_gb $(( 15 * GB + GB * 98 / 100 )))" = "16.0" ]
}
