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
