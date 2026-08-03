#!/usr/bin/env bash
# ============================================================
# Regenerate the seeded database fixture (§10.4).
#
# Dumps the database of a *running, installed* stack into
# docker/ci/fixtures/seeded-wiki.sql.gz — the "previous release" snapshot that
# scripts/ci/t5-migration.sh runs update.php against.
#
# Run once per release, by hand, from a wiki you trust. Never in CI: a fixture
# that regenerates itself records whatever the code does today, which is the
# opposite of what a migration test is for. Same contract as the golden files.
#
#   scripts/ci/make-db-fixture.sh            from the stack in this directory
#   scripts/ci/make-db-fixture.sh --out DIR  write somewhere else
#
# ─── What is in the fixture, and what is not ────────────────────────
#
# Everything that is content or configuration: pages, revisions, text, users,
# groups, permissions, categories, SMW data, BlueSpice tables. That is the
# point — update.php against an empty schema proves nothing that T3 does not
# already prove.
#
# Excluded *data* (structure is kept, so update.php still sees every table):
# the job queue, objectcache, searchindex, the process table and the
# ExtendedSearch trace. All are regenerable caches or queues; the job table
# alone is 662 rows of perpetual `invokeRunner` triggers (see the Wave 4 notes)
# and would be the largest thing in the file while proving nothing.
#
# Scrubbed: password hashes, user tokens, e-mail addresses, e-mail
# confirmation tokens, bot passwords and OATH (2FA) secrets. The fixture is
# committed to a public repository, and a password hash from a real install is
# a credential even when the wiki it came from is gone. The scrub happens in a
# scratch database and the file is dumped from *that*, so the committed values
# are already empty rather than empty-once-loaded — see the long note below.
#
# Exit: 0 written · 1 could not dump · 2 bad usage
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT" || exit 2

OUT_DIR="docker/ci/fixtures"
DB_NAME="${HDP_DB_NAME:-bluespice}"

while [ $# -gt 0 ]; do
    case "$1" in
        --out) shift; [ $# -gt 0 ] || { echo "--out needs a directory" >&2; exit 2; }; OUT_DIR="$1" ;;
        -h|--help) sed -n '2,38p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "make-db-fixture.sh: unknown option '$1'" >&2; exit 2 ;;
    esac
    shift
done

say() { printf '\033[0;36m[fixture]\033[0m %s\n' "$*"; }
fail() { printf '\033[0;31m[fixture] %s\033[0m\n' "$*" >&2; }

command -v docker >/dev/null 2>&1 || { fail "no docker on PATH"; exit 2; }
docker compose ps mariadb >/dev/null 2>&1 || { fail "no compose project here"; exit 2; }

# Data excluded; structure kept. Keep this list in step with the comment above
# and with tests/integration/test_db_migration.py, which asserts the fixture
# still carries content in the tables that matter.
TRANSIENT=(job objectcache searchindex processes bs_extendedsearch_trace)

mkdir -p "$OUT_DIR"
SQL="$(mktemp)"
trap 'rm -f "$SQL"' EXIT

# The password comes from the container's own environment, so it never lands
# in this shell's argv or in the host's process list.
dump_args=(--single-transaction --no-tablespaces --skip-comments --skip-dump-date
           --default-character-set=binary --hex-blob)
ignore_args=()
for t in "${TRANSIENT[@]}"; do ignore_args+=("--ignore-table=${DB_NAME}.${t}"); done

say "dumping structure for the transient tables"
docker compose exec -T mariadb sh -c \
    "mysqldump -uroot -p\"\$MARIADB_ROOT_PASSWORD\" ${dump_args[*]} --no-data $DB_NAME ${TRANSIENT[*]}" \
    > "$SQL" || { fail "structure dump failed"; exit 1; }

say "dumping everything else, with data"
docker compose exec -T mariadb sh -c \
    "mysqldump -uroot -p\"\$MARIADB_ROOT_PASSWORD\" ${dump_args[*]} ${ignore_args[*]} $DB_NAME" \
    >> "$SQL" || { fail "data dump failed"; exit 1; }

[ -s "$SQL" ] || { fail "the dump is empty"; exit 1; }

# ─── Credential scrub ───────────────────────────────────────────────
# The scrub has to happen in a *database*, not by appending UPDATE statements
# to the dump, and the difference is the whole point: the file is the artifact.
# `--hex-blob` writes user_password as 0x3A70626B646632…, which decodes to
# `:pbkdf2:sha512:30000:64:…` — the real hash from a real install, committed to
# a public repository. Statements that scrub it *at load time* leave it sitting
# in the file for anyone who reads the file instead of loading it.
#
# So: load into a scratch database, scrub there, dump that. The values in the
# committed file are then already empty, and the assertion at the end of this
# script proves it rather than assuming it.
# The scratch database name is plain [a-z_0-9], so nothing here needs quoting
# in SQL. Every statement goes in on stdin rather than through -e: this command
# crosses bash -> docker -> sh -> mysql, and a backtick that survives one layer
# too many is a command substitution in a shell running as root inside the
# database container.
SCRATCH="hdp_fixture_scrub_$$"
say "loading into scratch database $SCRATCH to scrub credentials"

mysql_root() {
    docker compose exec -T mariadb sh -c \
        "mysql -uroot -p\"\$MARIADB_ROOT_PASSWORD\" ${1:-}"
}

drop_scratch() { printf 'DROP DATABASE IF EXISTS %s;\n' "$SCRATCH" | mysql_root >/dev/null 2>&1; }
trap 'rm -f "$SQL" "${SQL}.scrubbed"; drop_scratch' EXIT

printf 'CREATE DATABASE %s;\n' "$SCRATCH" | mysql_root >/dev/null 2>&1 \
    || { fail "could not create $SCRATCH"; exit 1; }
mysql_root "$SCRATCH" < "$SQL" || { fail "could not load the dump into $SCRATCH"; exit 1; }

mysql_root "$SCRATCH" <<'SCRUB' || { fail "the scrub failed"; exit 1; }
UPDATE `user` SET user_password = '', user_newpassword = '', user_email = '',
                  user_email_token = NULL, user_token = '';
DELETE FROM `user_properties` WHERE up_property LIKE '%token%';
SCRUB

# 2FA secrets and API credentials. Each exists only on some installs, so a
# missing table must not fail the dump.
for table in oathauth_users bot_passwords oauth_accepted_consumer oauth_registered_consumer; do
    if grep -q "CREATE TABLE \`$table\`" "$SQL"; then
        printf 'DELETE FROM `%s`;\n' "$table" | mysql_root "$SCRATCH" && say "scrubbed $table"
    fi
done

say "re-dumping the scrubbed database"
docker compose exec -T mariadb sh -c \
    "mysqldump -uroot -p\"\$MARIADB_ROOT_PASSWORD\" ${dump_args[*]} $SCRATCH" \
    > "${SQL}.scrubbed" || { fail "re-dump failed"; exit 1; }
[ -s "${SQL}.scrubbed" ] || { fail "the scrubbed dump is empty"; exit 1; }

# Strip the scratch database's name out of the SQL.
#
# This is not cosmetic. DynamicPageList3 creates a VIEW (`dpl_clview`), and
# MariaDB re-qualifies a view's column references with the *current* database
# when it dumps it — so a dump taken from the scratch database defines the view
# in terms of `hdp_fixture_scrub_12345`.`categorylinks`, and loading it into
# `bluespice` fails with `ERROR 1054 Unknown column`. Found by running
# t5-migration.sh, not by reading the dump.
#
# Removing the qualifier entirely (rather than rewriting it to `bluespice`)
# leaves the view resolving against whatever database it is created in, which
# keeps the fixture loadable under a non-default HDP_DB_NAME.
sed "s/\`${SCRATCH}\`\.//g" "${SQL}.scrubbed" > "$SQL"
rm -f "${SQL}.scrubbed"
if grep -q "$SCRATCH" "$SQL"; then
    fail "the scratch database name survives in the dump — it would not load elsewhere"
    exit 1
fi
drop_scratch

# The safety net. `:pbkdf2` hex-encoded is 3A70626B646632, `:bcrypt` is
# 3A626372797074. If either survives, something changed and the file must not
# be committed — this is the one failure mode nobody notices by reading a
# passing test.
if grep -qiE '3a70626b646632|3a626372797074|[$]2y[$]|:pbkdf2:' "$SQL"; then
    fail "a password hash survived the scrub — refusing to write the fixture"
    exit 1
fi
say "no password hash survives in the dump"

# ─── Facts about what was captured ──────────────────────────────────
query() {
    docker compose exec -T mariadb sh -c \
        "mysql -uroot -p\"\$MARIADB_ROOT_PASSWORD\" -N -B -e \"$1\" $DB_NAME" 2>/dev/null | tr -d '\r'
}
TABLES="$(query 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')"
PAGES="$(query 'SELECT COUNT(*) FROM page')"
REVISIONS="$(query 'SELECT COUNT(*) FROM revision')"
USERS="$(query 'SELECT COUNT(*) FROM user')"
# From VERSIONS.yml rather than from the container: the version-consistency
# gate already proves that value equals MW_VERSION in app/includes/Defines.php,
# which is the code the running container has bind-mounted. One source, and one
# gate keeping it true.
MW_VERSION="$(python3 scripts/lib/versions.py get mw_core 2>/dev/null || echo unknown)"

say "writing $OUT_DIR/seeded-wiki.sql.gz"
gzip -9 -c "$SQL" > "$OUT_DIR/seeded-wiki.sql.gz"
SHA="$(sha256sum "$OUT_DIR/seeded-wiki.sql.gz" | cut -d' ' -f1)"
BYTES="$(wc -c < "$OUT_DIR/seeded-wiki.sql.gz" | tr -d ' ')"

python3 - "$OUT_DIR/seeded-wiki.meta.json" <<PY
import json, sys
json.dump({
    "description": [
        "What scripts/ci/t5-migration.sh runs update.php against: a real wiki's",
        "database, captured before an upgrade. Regenerate with",
        "scripts/ci/make-db-fixture.sh, by hand, once per release.",
        "Credentials are scrubbed in a scratch database before the file is",
        "written, so the values here are already empty - see that script.",
    ],
    "generated": "$(date -u +%Y-%m-%d)",
    "mw_core": "${MW_VERSION:-unknown}",
    "bluespice": "$(python3 scripts/lib/versions.py get bluespice 2>/dev/null || echo unknown)",
    "tables": int("${TABLES:-0}" or 0),
    "pages": int("${PAGES:-0}" or 0),
    "revisions": int("${REVISIONS:-0}" or 0),
    "users": int("${USERS:-0}" or 0),
    "bytes_gz": int("${BYTES:-0}" or 0),
    "sha256": "$SHA",
    "data_excluded": [$(printf '"%s",' "${TRANSIENT[@]}")],
}, open(sys.argv[1], "w"), indent=2)
open(sys.argv[1], "a").write("\n")
PY

say "done: $TABLES tables, $PAGES pages, $REVISIONS revisions, $USERS users, $BYTES bytes gzipped"
say "MediaWiki $MW_VERSION"
