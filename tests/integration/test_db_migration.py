"""T5 — upgrading an existing wiki, not installing a new one.

T3 proves `install.php` and `update.php` work against an empty database. Every
real operator does the other thing: runs the new code against a database that
already has pages, users, permissions and years of rows in it. None of the code
that *migrates* data is reached by a fresh install, so until this tier existed
the answer to "does an upgrade work" was untested.

`scripts/ci/t5-migration.sh` produces the state these assertions need: install
normally, drop the database, load `docker/ci/fixtures/seeded-wiki.sql.gz` — a
real wiki captured before the upgrade — and run `update.php` against it.

Every test here carries the `migration` marker, so neither T3 nor T4 collects
them: against a fresh install they would be asserting the opposite of what they
mean.
"""
import hashlib
import json
import re

import pytest

pytestmark = pytest.mark.migration

# update.php is chatty and some of its noise contains the word "error" in
# harmless contexts (an index named *_error, a class name). These are the
# patterns that mean the migration itself went wrong.
FAILURE_PATTERNS = [
    r"\bDBQueryError\b",
    r"\bDBConnectionError\b",
    r"\bFatal error\b",
    r"\bUncaught \w*(Exception|Error)\b",
    r"\bPHP Fatal\b",
    r"\bCall to undefined\b",
    r"Error: \d+ ",          # the "Error: 1064 You have an error in your SQL" shape
    r"\[ERROR\]",
]


# ─── the fixture itself ─────────────────────────────────────────────

def test_the_fixture_matches_its_recorded_checksum(repo_root, fixture_meta):
    """A silently-corrupted fixture would make every assertion below meaningless."""
    path = repo_root / "docker" / "ci" / "fixtures" / "seeded-wiki.sql.gz"
    digest = hashlib.sha256(path.read_bytes()).hexdigest()
    assert digest == fixture_meta["sha256"], (
        f"{path.name} does not match seeded-wiki.meta.json. Regenerate both together "
        f"with scripts/ci/make-db-fixture.sh; never edit one by hand."
    )


def test_the_fixture_was_captured_from_the_declared_release(repo_root, fixture_meta):
    """The snapshot has to say which release it came from, and be right.

    A migration test whose input is 'some database from some version' cannot
    tell you what it proved.
    """
    declared = (repo_root / "VERSIONS.yml").read_text(encoding="utf-8")
    assert re.search(rf"^mw_core:\s*'{re.escape(fixture_meta['mw_core'])}'",
                     declared, re.M), (
        f"the fixture was captured from MediaWiki {fixture_meta['mw_core']}, which is "
        f"not what VERSIONS.yml declares. After an upgrade, regenerate the fixture "
        f"from the OLD release before bumping — that is what makes it a migration test."
    )


def test_the_fixture_carries_real_content(fixture_meta):
    """An empty snapshot would pass every assertion below and prove nothing."""
    assert fixture_meta["pages"] >= 100, fixture_meta
    assert fixture_meta["revisions"] >= 100, fixture_meta
    assert fixture_meta["tables"] >= 190, fixture_meta
    assert fixture_meta["users"] >= 1, fixture_meta


def test_no_password_hash_is_committed_in_the_fixture(repo_root):
    """The file is public. A hash from a real install is a credential.

    scripts/ci/make-db-fixture.sh scrubs in a scratch database and refuses to
    write a file that still contains one; this is the assertion that keeps
    being true if somebody regenerates the fixture another way.
    """
    import gzip

    path = repo_root / "docker" / "ci" / "fixtures" / "seeded-wiki.sql.gz"
    with gzip.open(path, "rt", encoding="utf-8", errors="replace") as fh:
        sql = fh.read()
    # ':pbkdf2' and ':bcrypt' as MediaWiki writes them, and as --hex-blob
    # encodes them.
    for needle in (":pbkdf2:", ":bcrypt:", "3A70626B646632", "3A626372797074"):
        assert needle.lower() not in sql.lower(), (
            f"{needle!r} appears in the committed fixture — a password hash survived "
            f"the scrub in scripts/ci/make-db-fixture.sh"
        )


# ─── the migration ──────────────────────────────────────────────────

def test_update_php_exited_zero(migrated):
    assert migrated["exit"] == 0, (
        f"update.php exited {migrated['exit']} against the seeded snapshot.\n"
        f"Tail of {migrated['path']}:\n{migrated['log'][-3000:]}"
    )


def test_update_php_reported_no_failures(migrated):
    hits = [line for line in migrated["log"].splitlines()
            if any(re.search(p, line) for p in FAILURE_PATTERNS)]
    assert not hits, (
        "update.php exited 0 but its log contains failures — exit code alone is not "
        "enough here, since MediaWiki's updater reports several classes of problem "
        "and carries on:\n  " + "\n  ".join(hits[:20])
    )


def test_update_php_actually_did_something(migrated):
    """Guards against a vacuous pass.

    An updater that connected to the wrong database, or found nothing to do
    because the snapshot never loaded, produces a short clean log and exit 0 —
    indistinguishable from success unless you look for the work.
    """
    log = migrated["log"]
    assert len(log.strip()) > 200, f"update.php produced almost no output:\n{log!r}"
    assert re.search(r"\bdone\b|\.\.\.done|Done\b", log), (
        "update.php's log has no completion marker at all, which is what an "
        f"updater that never ran looks like:\n{log[-2000:]}"
    )


# ─── the wiki afterwards ────────────────────────────────────────────

def test_the_content_survived_the_migration(migrated, mw_sql, fixture_meta):
    """Every page in the snapshot is still there afterwards.

    This is the assertion the whole tier exists for: not "the updater was
    quiet" but "the data came through".
    """
    out = mw_sql("SELECT COUNT(*) AS n FROM page")
    match = re.search(r"\b(\d+)\b", out.replace("n", " "))
    assert match, f"could not read a page count from sql.php:\n{out[-1000:]}"
    after = int(match.group(1))
    assert after >= fixture_meta["pages"], (
        f"the snapshot had {fixture_meta['pages']} pages and the migrated wiki has "
        f"{after}. update.php exited 0, so this is silent data loss."
    )


def test_the_schema_is_at_least_what_the_snapshot_had(migrated, mw_sql):
    out = mw_sql("SELECT COUNT(*) AS n FROM information_schema.tables "
                 "WHERE table_schema = DATABASE()")
    match = re.search(r"\b(\d+)\b", out.replace("n", " "))
    assert match, f"could not read a table count from sql.php:\n{out[-1000:]}"
    after = int(match.group(1))
    assert after >= migrated["before_tables"], (
        f"the snapshot had {migrated['before_tables']} tables and the migrated "
        f"database has {after} — update.php dropped tables"
    )


def test_a_seeded_page_still_renders_after_the_migration(migrated, wiki):
    """Authenticated, and through MediaWiki rather than through SQL.

    A row surviving in the `page` table is not the same as a page a logged-in
    user can read: the revision, the content model, the text store and the
    permissions all have to have come through too. This is why
    t5-migration.sh resets the Admin password after the upgrade — the
    fixture's hashes are scrubbed, and without a login the strongest available
    assertion would be an anonymous 200, which this wiki returns for a login
    prompt.
    """
    titles = wiki.all_pages(12)
    assert titles, "ns 12 (Help) has no pages after the migration — the content is gone"
    title = sorted(titles)[0]
    data = wiki.api(action="query", prop="revisions", rvprop="content",
                    rvslots="main", titles=title)
    pages = data.get("query", {}).get("pages", [])
    assert pages and not pages[0].get("missing"), (
        f"{title!r} is not readable after the migration: {json.dumps(data)[:800]}"
    )
    content = pages[0]["revisions"][0]["slots"]["main"]["content"]
    assert len(content) > 200, f"{title!r} survived but its content did not"


def test_special_preferences_still_loads_after_the_migration(migrated, wiki):
    """The Wave 0 Issue 1 contract, re-asserted on an upgraded wiki.

    Special:Preferences is the page that broke for every logged-in user when
    the vendored Vector skin lost 59 files. An upgrade is the other way that
    can happen, so it is worth one more assertion here.
    """
    r = wiki.fetch("/index.php/Special:Preferences")
    assert r.status == 200, (
        f"Special:Preferences returned HTTP {r.status} after the migration.\n"
        f"First 500 bytes: {r.text[:500]!r}"
    )
    assert "wgUserName" in r.text, (
        "Special:Preferences came back 200 but not rendered for Admin — logged out, "
        "this wiki answers with a healthy 200 login prompt, so the status code alone "
        "proves nothing. Did changePassword.php run after update.php?"
    )
    assert len(r.body) >= 100 * 1024, (
        f"Special:Preferences is only {r.kib} KiB after the migration; a healthy "
        f"render is several hundred KiB (418 KiB on the Wave 2 clean box)"
    )
