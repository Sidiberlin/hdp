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

# ─── the T4 disk precondition ───────────────────────────────────────
#
# Split out of scripts/ci/t4-smoke.sh so the arithmetic can be tested against
# the two data points that produced it, without booting a stack. See
# tests/bats/t4_disk_guard.bats.
#
# The number T4 needs is not a property of the stack alone. OpenSearch stops
# accepting writes when the filesystem holding its data crosses the flood-stage
# watermark — 95% by default — so the stack has to fit *and* leave the last 5%
# of the filesystem untouched. Those are two different quantities and the old
# flat 14 GB floor conflated them, which is why it passed a runner that then
# failed:
#
#   run 30873842113, branch, 34 GB free   passed  (`disk: 34 GB free … need 14`)
#   run 30882658336, main,   15 GB free   failed  — 429 cluster_block_exception,
#                                         `disk usage exceeded flood-stage
#                                         watermark, index has
#                                         read-only-allow-delete block`
#
# The runner is a 72 GB filesystem, so 5% of it is 3.6 GB that OpenSearch will
# never let the stack have. The 15 GB failure is therefore also the first real
# measurement of what the stack consumes on a runner: it got to within 3.6 GB
# of full, so it wrote at least 15 − 3.6 = 11.4 GB. That corroborates the
# component-by-component estimate the old comment carried — haystack 2.57,
# opensearch 2.47, the two PHP bases 1.04 + 1.51, mariadb 0.47, chatbot-proxy
# 0.18, ~1.7 of model weights, plus build scratch — and STACK_NEED_GB below is
# 12 rather than 14 because the watermark is now added on top instead of being
# folded in.
#
# 12 + 3.6 = 15.6 GB, which "catches" the 15 GB run by 0.6 GB. That is not a
# guard, it is a coin toss on a number nobody measured to a tenth of a GB, so
# the requirement also carries a 20 GB floor. On this runner the floor is what
# fires; the percentage term is what keeps the guard honest on a filesystem big
# enough for 5% to exceed 8 GB, where a flat floor would be the thing that is
# wrong.

# hdp_disk_required_kb <filesystem-total-kb> [stack-need-gb] [floor-gb]
#
# Free space T4 requires, in KB: the stack's own footprint plus the slice of
# the filesystem OpenSearch's flood-stage watermark reserves, never less than
# the floor.
#
# All integer arithmetic, and deliberately divides before it multiplies: a
# filesystem total in KB times 5 overflows a 32-bit shell somewhere north of
# 400 GB, and this is a check that must not itself become the bug.
hdp_disk_required_kb() {
    local total_kb="$1"
    local stack_gb="${2:-12}"
    local floor_gb="${3:-20}"
    # OpenSearch blocks writes at 95%, so the top 5% is not ours to spend.
    local reserved_kb=$(( total_kb / 100 * 5 ))
    local need_kb=$(( stack_gb * 1024 * 1024 + reserved_kb ))
    local floor_kb=$(( floor_gb * 1024 * 1024 ))
    [ "$need_kb" -lt "$floor_kb" ] && need_kb="$floor_kb"
    printf '%s' "$need_kb"
}

# hdp_gb <kb> — KB rendered as GB to one decimal, without bc or awk.
#
# One decimal because the whole point of the numbers above is the 0.6 GB
# between "fits" and "OpenSearch goes read-only an hour into the job", and
# truncating 15.6 to 15 in the message would hide exactly that.
#
# Rounds to nearest rather than truncating, with the carry that implies. This
# prints a *requirement*: truncation would report 15.5 for a threshold of 15.6
# and send somebody off to free 15.5 GB, which is the same class of
# off-by-a-little that the old flat floor was.
hdp_gb() {
    local mb=$(( $1 / 1024 ))
    local gb=$(( mb / 1024 ))
    local tenths=$(( (mb % 1024 * 10 + 512) / 1024 ))
    if [ "$tenths" -ge 10 ]; then
        gb=$(( gb + 1 ))
        tenths=0
    fi
    printf '%d.%d' "$gb" "$tenths"
}
