#!/usr/bin/env bats
# Pin: update.sh's two attach-capable docker invocations — the setup.sh exec
# and the mysqldump exec (D5 of .planning/features/update-mechanism/PLAN.md,
# count settled at 2 by §9 Q2's ratification: the pre-update database dump
# ships) — survive a pipe-fed (`curl … | bash`) invocation exactly like
# install.sh's own guarded sites. Sibling of tests/bats/install_pipe.bats;
# a separate file per D5's "mirror bats file" decision rather than a shared
# helper, because both installers must stay single self-contained files that
# survive `curl | bash` on their own — see that decision's rationale in
# PLAN.md §4 D5.
#
# Same three disciplines as install_pipe.bats:
#   control    proves the mock drains — if this ever passes with AFTER
#              visible, the mock stopped draining and the whole file is vacuous
#   fix shape  the guarded invocation must leave the script intact
#   static pin greps update.sh for the tested shape; the mock tests cannot see
#              update.sh, so without this the file could drift back to an
#              unguarded invocation while the suite stays green

setup() {
    # Per-test mock in $BATS_TEST_TMPDIR — not the shared helpers/bin, which
    # belongs to the infisical-loader suite only.
    BIN="$BATS_TEST_TMPDIR/bin"
    mkdir -p "$BIN"
    cat > "$BIN/docker" <<'MOCK'
#!/bin/sh
# Drains stdin like an attached `docker compose exec`: -T declines the
# pseudo-TTY but does NOT detach stdin (see install_pipe.bats for the live
# repro this mirrors). A < /dev/null redirect on the invocation is what
# actually protects the script; this mock then drains /dev/null instead.
case "$1 $2" in
  "compose exec")
    cat > /dev/null
    exit 0 ;;
esac
exit 0
MOCK
    chmod +x "$BIN/docker"
    PATH="$BIN:$PATH"
    export PATH
}

@test "control: an unguarded compose exec drains a pipe-fed script" {
    run bash -c 'printf "%s\n" \
        "echo BEFORE" \
        "docker compose exec mediawiki bash /setup.sh" \
        "echo AFTER" | bash -s'
    [ "$status" -eq 0 ]
    [[ "$output" == *BEFORE* ]]
    [[ "$output" != *AFTER* ]]
}

@test "fix shape: -T + </dev/null keeps every line after the docker exec" {
    run bash -c 'printf "%s\n" \
        "echo BEFORE" \
        "docker compose exec -T mediawiki bash /setup.sh < /dev/null" \
        "echo AFTER" | bash -s'
    [ "$status" -eq 0 ]
    [[ "$output" == *BEFORE* ]]
    [[ "$output" == *AFTER* ]]
}

@test "finding pin: -T WITHOUT the redirect still drains — the redirect is the guard" {
    run bash -c 'printf "%s\n" \
        "echo BEFORE" \
        "docker compose exec -T mediawiki bash /setup.sh" \
        "echo AFTER" | bash -s'
    [ "$status" -eq 0 ]
    [[ "$output" == *BEFORE* ]]
    [[ "$output" != *AFTER* ]]
}

@test "update.sh: every compose run/exec invocation carries -T and </dev/null" {
    # Same anchor discipline as install_pipe.bats's static pin, re-run over
    # update.sh. Prints every invocation missing either guard; fails on
    # n == 0 (dead anchor) or n != 2 — the setup.sh exec and the mysqldump
    # exec are the only two attach-capable sites update.sh has (D5, settled
    # by §9 Q2). A different count is a design change to raise, not a number
    # to edit into this test.
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
            else if (n != 2) print "COUNT: expected 2 run/exec invocations, saw " n
        }' "$BATS_TEST_DIRNAME/../../update.sh"
    [ "$status" -eq 0 ]
    [ -z "$output" ]
}
