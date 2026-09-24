#!/usr/bin/env bats
# Pin: a pipe-fed (`curl … | bash`) installer survives its docker compose
# run AND exec calls — the v5.1.9-QoL3 Fix 1 shape and its post-release
# extension: -T plus < /dev/null on install.sh's predownload_models run,
# the first-boot setup exec, and the ingestion exec.
#
# The mock docker built in setup() drains stdin on `compose run` and
# `compose exec` whenever it is attached, exactly as the compose client
# does: -T declines the pseudo-TTY but does NOT detach stdin (proven live
# on the relqa box — an `exec -T` with no redirect drained the pipe exactly
# like an attached run), so the < /dev/null redirect on the invocation is
# the guard that actually protects the script pipe. Without it docker
# consumes the pipe bash is reading the script from, bash hits EOF, and
# every line after the call is gone — exit 0, no error message, the
# adopter symptom.
#
# Seven tests, one per discipline (see infisical_loader.bats):
#   control    proves the mock drains — if it ever passes with AFTER visible,
#              the mock has stopped draining and this whole file is vacuous
#              (one row per shape, run and exec)
#   fix shape  the mirror: the guarded invocation must leave the script
#              intact (run + exec), plus the finding row that pins WHY the
#              redirect is load-bearing: -T without it still drains
#   static pin greps install.sh for the tested shapes — the mock tests cannot
#              see install.sh, so without this the file could drift back to
#              an unguarded invocation while the suite stays green

setup() {
    # Per-test mock in $BATS_TEST_TMPDIR, NOT in tests/bats/helpers/bin —
    # that shared dir holds the loader suite's curl double and nothing else.
    BIN="$BATS_TEST_TMPDIR/bin"
    mkdir -p "$BIN"
    cat > "$BIN/docker" <<'MOCK'
#!/bin/sh
# Drains stdin like an attached `docker compose run`/`exec`: the compose
# client consumes the caller's stdin to EOF whenever stdin is attached,
# and -T only declines the pseudo-TTY — it does NOT detach stdin (live
# A/B/C on the relqa box: exec -T with no redirect drained the pipe
# exactly like an attached run). A < /dev/null redirect on the invocation
# is what protects the script: this mock then drains /dev/null instead.
# Every other docker invocation here is a no-op success.
case "$1 $2" in
  "compose run"|"compose exec")
    cat > /dev/null
    exit 0 ;;
esac
exit 0
MOCK
    chmod +x "$BIN/docker"
    # The mock shadows any real docker for this test only.
    PATH="$BIN:$PATH"
    export PATH
}

@test "control: an unguarded compose run drains a pipe-fed script" {
    # The adopter symptom, stated as a test. Bash reading the script from the
    # pipe loses every line after the unguarded docker call and still exits 0.
    run bash -c 'printf "%s\n" \
        "echo BEFORE" \
        "docker compose run --rm --no-deps haystack -c pass" \
        "echo AFTER" | bash -s'
    [ "$status" -eq 0 ]
    [[ "$output" == *BEFORE* ]]
    [[ "$output" != *AFTER* ]]
}

@test "control: an unguarded compose exec drains a pipe-fed script" {
    # Same statement for the exec shape — the call that drained the live
    # quickstart run AFTER setup had already completed.
    run bash -c 'printf "%s\n" \
        "echo BEFORE" \
        "docker compose exec mediawiki bash /setup.sh" \
        "echo AFTER" | bash -s'
    [ "$status" -eq 0 ]
    [[ "$output" == *BEFORE* ]]
    [[ "$output" != *AFTER* ]]
}

@test "fix shape: -T + </dev/null keeps every line after the docker run" {
    # The mirror of the run control. Without it, a mock that never drained
    # would satisfy the control vacuously; with it, the guarded shape is the
    # one thing that must survive.
    run bash -c 'printf "%s\n" \
        "echo BEFORE" \
        "docker compose run --rm --no-deps -T --entrypoint python3 haystack -c pass < /dev/null" \
        "echo AFTER" | bash -s'
    [ "$status" -eq 0 ]
    [[ "$output" == *BEFORE* ]]
    [[ "$output" == *AFTER* ]]
}

@test "fix shape: -T + </dev/null keeps every line after the docker exec" {
    # The mirror of the exec control — the shape the setup/ingestion execs
    # must carry.
    run bash -c 'printf "%s\n" \
        "echo BEFORE" \
        "docker compose exec -T mediawiki bash /setup.sh < /dev/null" \
        "echo AFTER" | bash -s'
    [ "$status" -eq 0 ]
    [[ "$output" == *BEFORE* ]]
    [[ "$output" == *AFTER* ]]
}

@test "finding pin: -T WITHOUT the redirect still drains — the redirect is the guard" {
    # The exact shape that shipped at 41a46d43c:1258/:1298 and silently
    # truncated the live quickstart run after setup completed (no ready
    # block, no Login line, exit 0). If this row ever FAILS, the mock has
    # been "fixed" to stop draining on -T — re-read the A/B/C repro before
    # touching it: -T does not detach stdin, only the redirect does.
    run bash -c 'printf "%s\n" \
        "echo BEFORE" \
        "docker compose exec -T mediawiki bash /setup.sh" \
        "echo AFTER" | bash -s'
    [ "$status" -eq 0 ]
    [[ "$output" == *BEFORE* ]]
    [[ "$output" != *AFTER* ]]
}

@test "install.sh: every compose run/exec invocation carries -T and </dev/null" {
    # Ties the real file to the shapes tested above. The anchor matches the
    # invocations themselves — the closing quote of "${COMPOSE_ARGS[@]}"
    # followed by `run` or `exec` — and never the info/printf lines, which
    # print ${COMPOSE_ARGS[*]} inside a string. Continuation lines (a line
    # ending in `\`) are joined first, so a multi-line invocation is judged
    # as the single command it is. Prints every invocation missing either
    # guard; also fails when the count is not exactly 4 (the predownload
    # run, the GPU-verification run added for the wheel-selection incident —
    # see tests/bats/gpu_wheel.bats, the setup exec, the ingestion exec) so a
    # NEW run/exec site cannot appear without extending this pin — and when
    # it is 0, which would mean the anchor died and the pin went vacuous.
    run awk '
        {
            if (cont) { line = line $0 } else { line = $0 }
            if (line ~ /\\$/) { line = substr(line, 1, length(line) - 1); cont = 1; next }
            cont = 0
            if (index(line, "COMPOSE_ARGS[@]}\" run") || index(line, "COMPOSE_ARGS[@]}\" exec")) {
                n++
                if (line !~ /-T/ || index(line, "< /dev/null") == 0)
                    print "UNGUARDED: " line
            }
        }
        END {
            if (n == 0) print "DEAD ANCHOR: no run/exec invocation matched"
            else if (n != 4) print "COUNT: expected 4 run/exec invocations, saw " n
        }' "$BATS_TEST_DIRNAME/../../install.sh"
    [ "$status" -eq 0 ]
    [ -z "$output" ]
}
