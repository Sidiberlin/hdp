#!/usr/bin/env bash
# ============================================================
# T5 — the migration job: does `update.php` upgrade an EXISTING wiki?
#
#   fresh volumes -> compose up -> setup.sh -> load the seeded snapshot ->
#   update.php -> assert -> teardown
#
# T3 proves a *fresh install* works. Every real HDP operator will instead run
# an upgrade against a database with years of pages, users and permissions in
# it, and nothing in this repository tested that path until now. `update.php`
# against an empty schema exercises none of the code that migrates rows.
#
#   scripts/ci/t5-migration.sh              the whole sequence
#   scripts/ci/t5-migration.sh --keep       leave the stack up afterwards
#   scripts/ci/t5-migration.sh --no-build   reuse images already built
#
# ─── Why it installs first, then overwrites the database ────────────
#
# The wiki cannot boot from the snapshot alone: `app/vendor/` is gitignored and
# created by composer, and `LocalSettings.php` is written by install.php. Both
# come from setup.sh. Committing a LocalSettings.php fixture would mean
# committing a file full of generated secrets *and* a second source of truth
# for what setup.sh produces, which would drift.
#
# So the sequence installs normally, then drops the database and loads the
# snapshot over it. What is under test afterwards is exactly the real upgrade:
# current code, current schema expectations, older data.
#
# ─── Why the Admin password is reset afterwards ─────────────────────
#
# The fixture's password hashes are scrubbed — it is committed to a public
# repository. Resetting the password after the migration is what lets the
# assertions log in, which is the difference between "update.php printed no
# errors" and "the upgraded wiki serves authenticated traffic".
#
# Exit: 0 migrated and asserted · 1 something failed · 2 cannot run
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT" || exit 1

# shellcheck source=scripts/ci/lib/stack.sh
. "$REPO_ROOT/scripts/ci/lib/stack.sh"

SERVICES="${HDP_T5_SERVICES:-mariadb opensearch mediawiki mediawiki-web}"
FIXTURE="docker/ci/fixtures/seeded-wiki.sql.gz"
DB_NAME="${HDP_DB_NAME:-bluespice}"
WIKI_URL="${HDP_WIKI_URL:-http://localhost:8080/w}"
KEEP=0
BUILD=1

while [ $# -gt 0 ]; do
    case "$1" in
        --keep)     KEEP=1 ;;
        --no-build) BUILD=0 ;;
        -h|--help)  sed -n '2,36p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "t5-migration.sh: unknown option '$1' (try --help)" >&2; exit 2 ;;
    esac
    shift
done

read -r -a SERVICE_LIST <<< "$SERVICES"

say()  { hdp_say "$@"; }
fail() { printf '\033[0;31mT5 FAILED: %s\033[0m\n' "$*" >&2; }

command -v docker >/dev/null 2>&1 || { fail "no docker on PATH"; exit 2; }
docker compose version >/dev/null 2>&1 || { fail "docker compose v2 is required"; exit 2; }
[ -f "$FIXTURE" ] || { fail "$FIXTURE is missing — regenerate it with scripts/ci/make-db-fixture.sh"; exit 2; }

UPDATE_LOG="$(mktemp -t hdp-update-XXXXXX.log)"
SETUP_LOG="$(mktemp -t hdp-setup-XXXXXX.log)"
CREATED_ENV=0

cleanup() {
    local rc=$?
    if [ "$KEEP" -eq 1 ]; then
        say "leaving the stack up (--keep). Tear it down with: docker compose down -v"
        [ "$CREATED_ENV" -eq 1 ] && say "kept the generated .env — it holds this stack's passwords"
    else
        say "tearing down"
        docker compose down -v --remove-orphans >/dev/null 2>&1
        [ "$CREATED_ENV" -eq 1 ] && rm -f "$REPO_ROOT/.env"
    fi
    rm -f "$UPDATE_LOG" "$SETUP_LOG"
    exit "$rc"
}
trap cleanup EXIT INT TERM

ENV_STATE="$(hdp_generate_env "$REPO_ROOT")" || { fail "could not prepare .env"; exit 2; }
if [ "$ENV_STATE" = "generated" ]; then
    CREATED_ENV=1
    say "generated a throwaway .env from .env.example"
    # A previous run's LocalSettings.php and seeding markers live in the
    # bind-mounted tree, not in the volumes, so they survive `down -v` and make
    # the next run assert against a wiki that was never installed or seeded.
    # See hdp_assert_fresh_tree in scripts/ci/lib/stack.sh.
    if ! hdp_assert_fresh_tree "$REPO_ROOT"; then
        fail "the working tree is not clean enough to install into"
        exit 2
    fi
else
    say "using the existing .env"
fi

# ─── boot + install ─────────────────────────────────────────────────
say "starting: ${SERVICE_LIST[*]}"
UP_ARGS=(up -d)
[ "$BUILD" -eq 1 ] && UP_ARGS+=(--build)
docker compose "${UP_ARGS[@]}" "${SERVICE_LIST[@]}" || { fail "docker compose up"; docker compose ps; exit 1; }

say "waiting for the web container to accept connections"
hdp_wait_for_web "$WIKI_URL" 300 || { fail "nothing answering at $WIKI_URL/"; docker compose logs --no-color --tail 60 mediawiki-web mediawiki; exit 1; }

say "running setup.sh (for vendor/ and LocalSettings.php)"
docker compose exec -T mediawiki bash /setup.sh 2>&1 | tee "$SETUP_LOG" >/dev/null
SETUP_EXIT="${PIPESTATUS[0]}"
say "setup.sh exit: $SETUP_EXIT"
if [ "$SETUP_EXIT" -ne 0 ]; then
    fail "setup.sh exited $SETUP_EXIT — the migration test needs a working install to upgrade"
    tail -40 "$SETUP_LOG"
    exit 1
fi

# ─── load the snapshot over the fresh install ───────────────────────
say "loading $FIXTURE over the freshly installed database"
mysql_root() {
    docker compose exec -T mariadb sh -c "mysql -uroot -p\"\$MARIADB_ROOT_PASSWORD\" ${1:-}"
}

# DROP + CREATE rather than loading over the top: the snapshot has to be the
# whole database, not a merge with whatever install.php just created. A table
# the old release did not have, left behind here, would make update.php look
# like it had already run.
printf 'DROP DATABASE %s; CREATE DATABASE %s CHARACTER SET binary;\n' "$DB_NAME" "$DB_NAME" \
    | mysql_root >/dev/null || { fail "could not reset $DB_NAME"; exit 1; }
gzip -dc "$FIXTURE" | mysql_root "$DB_NAME" || { fail "could not load the snapshot"; exit 1; }

BEFORE_PAGES="$(printf 'SELECT COUNT(*) FROM page;\n' | mysql_root "-N -B $DB_NAME" | tr -d '\r')"
BEFORE_TABLES="$(printf 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema="%s";\n' "$DB_NAME" \
    | mysql_root -N -B | tr -d '\r')"
say "snapshot loaded: $BEFORE_TABLES tables, $BEFORE_PAGES pages"

# ─── the thing under test ───────────────────────────────────────────
# Exactly the invocation docker/setup.sh line 399 uses, flags included. Two
# reasons it is not a plain `php maintenance/update.php`:
#
#   * `maintenance/run.php update.php` is the modern runner and the one this
#     distribution actually calls, so testing the other form would be testing a
#     path no operator takes.
#   * `--skip-config-validation` is load-bearing here and was found by running
#     this job without it: `$wgIllegalFileChars` is set by the vendored
#     NSFileRepo extension and has been deprecated since MediaWiki 1.41, so
#     update.php refuses to start — "Please correct the issue before running
#     update.php again" — and exits 1 before touching the database. That is a
#     pre-existing upstream deprecation, not a migration failure, and setup.sh
#     has always passed this flag. Dropping it here would make T5 red for a
#     reason T5 is not about.
say "running update.php against the snapshot (same invocation setup.sh uses)"
docker compose exec -T mediawiki php /var/www/html/w/maintenance/run.php update.php \
    --quick --skip-config-validation 2>&1 | tee "$UPDATE_LOG"
UPDATE_EXIT="${PIPESTATUS[0]}"
say "update.php exit: $UPDATE_EXIT"

# Reset the Admin password: the fixture's hashes are scrubbed, so without this
# the assertions could only make anonymous requests — and "the upgraded wiki
# still serves authenticated traffic" is the assertion worth having.
if [ "$UPDATE_EXIT" -eq 0 ]; then
    say "resetting the Admin password so the assertions can log in"
    docker compose exec -T mediawiki sh -c \
        'php /var/www/html/w/maintenance/run.php changePassword.php --user=Admin --password="$HDP_ADMIN_PASSWORD"' \
        >/dev/null 2>&1 || say "changePassword.php failed — the authenticated assertions will say so"
fi

# ─── assert ─────────────────────────────────────────────────────────
say "running the migration assertions"
HDP_UPDATE_LOG="$UPDATE_LOG" \
HDP_UPDATE_EXIT="$UPDATE_EXIT" \
HDP_MIGRATION_BEFORE_PAGES="$BEFORE_PAGES" \
HDP_MIGRATION_BEFORE_TABLES="$BEFORE_TABLES" \
HDP_WIKI_URL="$WIKI_URL" \
    scripts/ci/pytest.sh --tier migration
TEST_EXIT=$?

if [ "$TEST_EXIT" -eq 77 ]; then
    fail "the migration tier could not run, after this job started a stack for it"
    docker compose ps
    exit 1
fi
[ "$TEST_EXIT" -eq 0 ] || { fail "migration assertions"; exit 1; }

say "T5 PASSED"
exit 0
