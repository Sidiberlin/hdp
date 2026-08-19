#!/usr/bin/env bats
# Pin: a pipe-fed (`curl … | bash`) installer survives its docker compose run —
# the v5.1.9-QoL3 Fix 1 shape: -T plus </dev/null on install.sh's
# predownload_models invocation.
#
# The mock docker built in setup() drains stdin on `compose run` without -T,
# exactly as an attached compose run does: docker consumes the pipe bash is
# reading the script from, bash hits EOF, and every line after the call is
# gone — exit 0, no error message, the adopter symptom.
#
# Three tests, one per discipline (see infisical_loader.bats):
#   control    proves the mock drains — if it ever passes with AFTER visible,
#              the mock has stopped draining and this whole file is vacuous
#   fix shape  the mirror: the guarded invocation must leave the script intact
#   static pin greps install.sh for the tested shape — the mock tests cannot
#              see install.sh, so without this the file could drift back to
#              the unguarded invocation while the suite stays green

setup() {
    # Per-test mock in $BATS_TEST_TMPDIR, NOT in tests/bats/helpers/bin —
    # that shared dir holds the loader suite's curl double and nothing else.
    BIN="$BATS_TEST_TMPDIR/bin"
    mkdir -p "$BIN"
    cat > "$BIN/docker" <<'MOCK'
#!/bin/sh
# Drains stdin like an attached `docker compose run`: without -T compose
# attaches and consumes the caller's stdin to EOF; with -T it does not.
# Every other docker invocation here is a no-op success.
case "$1 $2" in
  "compose run")
    for a in "$@"; do [ "$a" = "-T" ] && exit 0; done
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

@test "fix shape: -T + </dev/null keeps every line after the docker call" {
    # The mirror of the control. Without it, a mock that never drained would
    # satisfy the control vacuously; with it, the guarded shape is the one
    # thing that must survive.
    run bash -c 'printf "%s\n" \
        "echo BEFORE" \
        "docker compose run --rm --no-deps -T --entrypoint python3 haystack -c pass < /dev/null" \
        "echo AFTER" | bash -s'
    [ "$status" -eq 0 ]
    [[ "$output" == *BEFORE* ]]
    [[ "$output" == *AFTER* ]]
}

@test "install.sh: the predownload invocation carries -T and </dev/null" {
    # Ties the real file to the shape tested above. -A2 spans the multi-line
    # continuation of the predownload_models compose run; the anchor includes
    # the closing quote of "${COMPOSE_ARGS[@]}" so it matches the invocation
    # itself and not the info line above it (which prints ${COMPOSE_ARGS[*]}
    # inside a string), and nothing else in install.sh.
    run grep -A2 'COMPOSE_ARGS\[@\]}" run --rm --no-deps' \
        "$BATS_TEST_DIRNAME/../../install.sh"
    [ "$status" -eq 0 ]
    [[ "$output" == *"-T"* ]]
    [[ "$output" == *"/dev/null"* ]]
}
