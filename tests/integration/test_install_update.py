"""3.2 — the install.php / update.php sequence.

`docker/setup.sh` wraps both. Wave 0 Issue 3 was that this script reported
"✓ Setup complete!" and exited 0 while three separate steps had failed, so the
assertions here are deliberately split between *what the run reported* and
*what the wiki actually looks like now*. Either alone has been wrong before.

The idempotency test is the one with teeth. `update.php` runs on every deploy
of a new BlueSpice version, and a schema migration that is not re-runnable
turns the second deploy into an outage. Nothing else in the repo exercises
that path.
"""
import re

import pytest

# Wave 1 and both Wave 2 clean-box runs all landed on exactly 198 tables. It is
# a baseline, not a law: a BlueSpice or MediaWiki upgrade that adds a table is
# a legitimate reason for this to change, and the fix is to update the constant
# in the same commit that takes the upgrade. Override for a one-off run with
# HDP_EXPECTED_TABLE_COUNT.
#
# 199 since the MediaWiki 1.43.9 / BlueSpice 5.1.9 upgrade. That bump carries
# mwstake/mediawiki-component-processmanager from 3.1.3 to 5.0.2, which adds
# `process_plugin_lock` (db/mysql/process_plugin_lock.sql) alongside the
# `processes` table it already owned. This assertion is what caught it.
EXPECTED_TABLE_COUNT = 199

# A representative slice rather than all 199: one table from each subsystem
# whose absence has previously meant a broken wiki rather than a missing
# feature. `bs_reminder`, `workflows_event` and `bs_whoisonline` are named
# because those three are exactly the tables cache/mw-dberror.log complains
# about during the install window — this is the assertion that says the
# complaints were transient and the tables did get created.
REQUIRED_TABLES = [
    # MediaWiki core
    "page", "revision", "text", "actor", "user", "slots", "content", "logging",
    # BlueSpice
    "bs_settings3", "bs_reminder", "bs_whoisonline", "bs_pageassignments",
    "bs_extendedsearch_history",
    # Semantic MediaWiki
    "smw_object_ids", "smw_prop_stats", "smw_di_wikipage",
    # Workflows / process
    "workflows_event", "workflows_state",
    # This fork's own table (the ChatBot extension)
    "bmbf_index_pages",
]


def _table_names(mw_sql):
    """Every table in the wiki's own database, as a set.

    sql.php prints PHP `print_r` output, so the values are scraped rather than
    parsed. Kept in one place so a format change breaks one function.
    """
    out = mw_sql(
        "SELECT table_name FROM information_schema.tables "
        "WHERE table_schema = DATABASE() ORDER BY table_name"
    )
    names = {m.group(1).strip() for m in re.finditer(r"\[table_name\] => (.+)", out)}
    assert names, f"could not parse any table name out of sql.php output:\n{out[:1000]}"
    return names


@pytest.fixture(scope="session")
def expected_table_count():
    import os

    return int(os.environ.get("HDP_EXPECTED_TABLE_COUNT", EXPECTED_TABLE_COUNT))


# ─── what the run reported ──────────────────────────────────────────
def test_setup_reported_success(setup_record):
    """setup.sh exited 0 and said so.

    Skipped when the tier is pointed at a stack this run did not install —
    scripts/ci/t3-integration.sh always provides the record, so in CI this
    never skips.
    """
    if setup_record is None:
        pytest.skip(
            "no setup.sh record (HDP_SETUP_EXIT/HDP_SETUP_LOG unset) — this "
            "stack was installed outside the test run. Use "
            "scripts/ci/t3-integration.sh to cover the install sequence."
        )
    assert setup_record["exit"] == 0, (
        f"setup.sh exited {setup_record['exit']}. Its summary line names the "
        f"failures; see {setup_record['path']}."
    )
    assert "Setup complete" in setup_record["log"], (
        "setup.sh exited 0 but never printed its completion summary — which is "
        "the Wave 0 Issue 3 signature, where the script claimed success from a "
        "half-finished run."
    )


def test_install_php_ran_and_completed(setup_record):
    if setup_record is None:
        pytest.skip("no setup.sh record — see test_setup_reported_success")
    log = setup_record["log"]
    # setup.sh skips the install when LocalSettings.php already exists, which
    # is correct behaviour but means this stack proves nothing about
    # install.php. Say which of the two happened rather than asserting both.
    if "skipping install" in log:
        pytest.skip(
            "LocalSettings.php already existed, so setup.sh skipped install.php. "
            "Only a stack installed from an empty volume exercises it."
        )
    assert "[2/4] MediaWiki installed." in log, (
        "setup.sh did not report a completed MediaWiki install. install.php "
        "runs under `set -e`, so a non-zero exit aborts before this line."
    )


def test_update_php_ran(setup_record):
    if setup_record is None:
        pytest.skip("no setup.sh record — see test_setup_reported_success")
    assert "[3/4] update.php complete." in setup_record["log"], (
        "setup.sh did not report update.php completing. It runs under `set -e` "
        "immediately after install.php, so this line is the only evidence in "
        "the log that the extension tables were created."
    )


# ─── what the wiki looks like now ───────────────────────────────────
def test_local_settings_exists_and_parses(mw_exec):
    probe = mw_exec("test", "-s", "/var/www/html/w/LocalSettings.php")
    assert probe.returncode == 0, (
        "LocalSettings.php is missing or empty. install.php writes it, so the "
        "install either never ran or failed partway."
    )
    lint = mw_exec("php", "-l", "/var/www/html/w/LocalSettings.php")
    assert lint.returncode == 0, (
        f"LocalSettings.php does not parse:\n{lint.stdout}\n{lint.stderr}"
    )


def test_local_settings_uses_the_bluespice_loader(mw_exec):
    """The post-install rewrite setup.sh performs actually took.

    BlueSpice loads its ~130 extensions from `settings.d/*.php`, not from the
    `wfLoadExtension()` calls the installer generates. setup.sh strips those
    lines and appends the loader; leaving both in place produces a
    "loaded twice" fatal at boot. Both halves are asserted because `sed -i`
    exits 0 when it matches nothing — the same silent-no-op failure mode that
    Wave 1 removed from this script's patch handling.
    """
    proc = mw_exec("cat", "/var/www/html/w/LocalSettings.php")
    assert proc.returncode == 0
    body = proc.stdout
    assert "LocalSettings.BlueSpice.php" in body, (
        "LocalSettings.php does not require the BlueSpice settings loader, so "
        "settings.d/*.php never runs and ~130 extensions are not loaded."
    )
    leftovers = [
        ln for ln in body.splitlines() if ln.lstrip().startswith("wfLoadExtension(")
    ]
    assert not leftovers, (
        "installer-generated wfLoadExtension() lines survived in "
        f"LocalSettings.php: {leftovers[:5]}. Together with the BlueSpice "
        "loader these cause a 'loaded twice' fatal."
    )


def test_update_php_created_every_required_table(mw_sql):
    tables = _table_names(mw_sql)
    missing = [t for t in REQUIRED_TABLES if t not in tables]
    assert not missing, (
        f"update.php did not create {len(missing)} expected table(s): {missing}. "
        f"The database has {len(tables)} tables."
    )


def test_table_count_matches_the_baseline(mw_sql, expected_table_count):
    tables = _table_names(mw_sql)
    assert len(tables) == expected_table_count, (
        f"the wiki has {len(tables)} tables, not the {expected_table_count} "
        f"recorded by Wave 1 and both Wave 2 clean-box runs. If a BlueSpice or "
        f"MediaWiki upgrade legitimately changed this, update "
        f"EXPECTED_TABLE_COUNT in this file as part of that commit."
    )


def test_update_php_is_idempotent(mw_exec, mw_sql):
    """Running update.php a second time changes nothing and exits 0.

    This is the deploy path: every BlueSpice upgrade re-runs update.php over a
    populated database. A migration that is not re-runnable fails here, on a
    disposable CI wiki, instead of on somebody's production one.

    Slow by nature — it walks every extension's update list — so it is the last
    test in the file rather than a fixture.
    """
    before = _table_names(mw_sql)

    proc = mw_exec(
        "php",
        "maintenance/run.php",
        "update.php",
        "--quick",
        "--skip-config-validation",
        timeout=1800,
    )
    assert proc.returncode == 0, (
        f"a second update.php run exited {proc.returncode}. The first run "
        f"succeeded, so this is a migration that cannot be replayed.\n"
        f"stdout tail:\n{proc.stdout[-3000:]}\nstderr tail:\n{proc.stderr[-2000:]}"
    )

    after = _table_names(mw_sql)
    assert after == before, (
        "the second update.php run changed the schema.\n"
        f"  added:   {sorted(after - before)}\n"
        f"  removed: {sorted(before - after)}"
    )
