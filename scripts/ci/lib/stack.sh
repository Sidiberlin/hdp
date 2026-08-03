#!/usr/bin/env bash
# ============================================================
# Shared stack plumbing for the two container jobs.
#
#   scripts/ci/t3-integration.sh   the wiki, four containers
#   scripts/ci/t4-smoke.sh         the full stack, seven containers
#
# Sourced, never executed. Everything here is the part of "boot a stack" that
# is identical between the two jobs: materialising a throwaway .env, and
# waiting for Apache to answer. The parts that differ — which services, what
# runs after the install, what the tests are — stay in the two callers, where
# they are readable.
#
# This exists because the alternative is two copies of the .env generator. That
# generator encodes two things learned the hard way (OpenSearch's password
# policy, and secrets never reaching argv), and a second copy is a second place
# for them to rot.
#
# Every function is prefixed `hdp_` and reads/writes the caller's variables
# explicitly through arguments, so sourcing this cannot quietly redefine
# something in the caller.
# ============================================================

# hdp_say <message…> — a section heading, matching both callers' output style.
hdp_say() { printf '\n\033[1m── %s\033[0m\n' "$*"; }

# hdp_generate_env <repo-root>
#
# Ensures a .env exists for compose to read. Prints `generated` on stdout when
# it created one (so the caller knows to delete it again) and `existing` when
# it left the developer's own file alone.
#
# compose declares `env_file: .env` and will not parse without one, and a CI
# runner has no .env. Passwords are generated rather than fixed, and written
# straight into the file by python — never passed through argv, where any other
# process on the box could read them out of /proc.
#
# The character-class requirement is not decoration: OpenSearch enforces a
# password policy (8+ characters, mixed classes) and *refuses to start*
# otherwise, which surfaces as an unhealthy container rather than as anything
# anybody would connect to a password.
hdp_generate_env() {
    local repo_root="$1"
    if [ -f "$repo_root/.env" ]; then
        echo existing
        return 0
    fi
    [ -f "$repo_root/.env.example" ] || {
        echo "hdp_generate_env: .env.example is missing" >&2
        return 2
    }
    cp "$repo_root/.env.example" "$repo_root/.env"
    local var secret
    for var in HDP_DB_ROOT_PASSWORD HDP_DB_PASSWORD HDP_ADMIN_PASSWORD HDP_OPENSEARCH_PASSWORD; do
        secret="CI$(head -c 18 /dev/urandom | base64 | tr -dc 'A-Za-z0-9')aA1!"
        python3 - "$repo_root/.env" "$var" "$secret" <<'PY'
import sys, pathlib
path, var, value = sys.argv[1], sys.argv[2], sys.argv[3]
p = pathlib.Path(path)
lines = p.read_text().splitlines()
out = [f"{var}={value}" if ln.startswith(var + "=") else ln for ln in lines]
if not any(ln.startswith(var + "=") for ln in lines):
    out.append(f"{var}={value}")
p.write_text("\n".join(out) + "\n")
PY
    done
    echo generated
}

# hdp_wait_for_web <url> <seconds>
#
# Waits for something to answer at <url>/. Returns 0 when it does.
#
# Deliberately not a health-status wait. Before setup.sh runs there is no
# LocalSettings.php, so /w/ returns 500 and mediawiki-web is *legitimately*
# unhealthy — reproduced on every genuinely fresh box since Wave 2. Waiting for
# "healthy" here hangs until the timeout on a perfectly good stack. What
# matters at this point is that something is listening.
hdp_wait_for_web() {
    local url="$1" budget="${2:-300}"
    local deadline=$(( SECONDS + budget ))
    until curl -s -o /dev/null --max-time 5 "$url/" || [ "$SECONDS" -ge "$deadline" ]; do
        sleep 3
    done
    curl -s -o /dev/null --max-time 5 "$url/"
}

# hdp_assert_fresh_tree <repo-root>
#
# Refuses to start when a previous run's state is still in the working tree.
# Returns 1 and explains; the caller decides whether that is fatal.
#
# `app/` is a bind mount, so `docker compose down -v` empties the database
# volume and leaves the *tree* untouched. Two kinds of leftover then make the
# next run lie about what it tested:
#
#   app/LocalSettings.php          setup.sh skips install.php, and update.php
#                                  authenticates with the previous run's
#                                  password against the new volume. Surfaces as
#                                  `DBConnectionError: Access denied`, which
#                                  reads exactly like a broken migration.
#   app/cache/.*-populated         setup.sh skips seeding the Main page, the
#                                  FAQ, the Site: pages and the Help docs, and
#                                  skips initBackends.php. The wiki comes up
#                                  empty against a fresh database and T3 fails
#                                  seven assertions about content that was
#                                  never written.
#
# Both were hit while validating Wave 5, in that order. Neither can happen in
# CI, where every run starts from a fresh checkout — which is exactly why they
# are worth a message rather than a debugging session.
#
# Nothing is deleted here: this is somebody's working tree.
hdp_assert_fresh_tree() {
    local repo_root="$1"
    local -a leftovers=()

    [ -f "$repo_root/app/LocalSettings.php" ] && leftovers+=("app/LocalSettings.php")
    local marker
    for marker in "$repo_root"/app/cache/.*-populated "$repo_root"/app/cache/.extendedsearch-initialized; do
        [ -e "$marker" ] && leftovers+=("app/cache/$(basename "$marker")")
    done

    [ "${#leftovers[@]}" -eq 0 ] && return 0

    echo "  A previous run left state in the working tree, and app/ is a bind mount," >&2
    echo "  so 'docker compose down -v' did not remove it:" >&2
    printf '    %s\n' "${leftovers[@]}" >&2
    echo "" >&2
    echo "  setup.sh would skip the install and the seeding, and this job would then" >&2
    echo "  assert against a wiki that was never populated." >&2
    echo "" >&2
    echo "  Clear it with:  git clean -xfd app/ && git checkout -- app/" >&2
    echo "  (that also removes app/vendor/, so composer runs again — a few minutes)" >&2
    return 1
}
