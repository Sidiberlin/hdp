"""3.4 — the `$wg`-prefix readback.

Configuration in this fork is layered three deep, and the layering is where the
bugs have been. The Wikimedia dev image's `PlatformSettings.php` runs first,
`LocalSettings.php` next, and this project's ~17 `app/settings.d/*.php` files
last — several of which exist purely to *undo* a dev-image default that breaks
BlueSpice in production. Asserting on the contents of a settings file therefore
proves nothing about the wiki: the question is always what the final value is.

So every test here reads the value out of a PHP process that has finished
loading all three layers (`mw_globals`, via `eval.php`), and where possible
cross-checks it against a second, independent view — the Action API's
`meta=siteinfo`, or the live database session. A config global and the API
disagreeing means one of them is lying, and it matters which.
"""
import re

import pytest

# Where the ingestion pipeline's namespace list is defined. Read out of the
# source rather than imported: ingest_hdp_wiki.py imports pymysql, requests and
# haystack at module scope, none of which this stdlib-only tier has. Parsing it
# still fails loudly if the constant is renamed or moved, which is the point.
INGEST_SOURCE = ("docker", "haystack", "ingest_hdp_wiki.py")
INDEXABLE_RE = re.compile(r"^INDEXABLE_NAMESPACES\s*=\s*\[([0-9,\s]+)\]", re.MULTILINE)

# Extensions whose absence is a broken wiki rather than a missing feature.
# Deliberately short: the full list is 169 entries and churns with every
# BlueSpice release, so a fixed full list would be a maintenance tax that
# catches nothing. These are the load-bearing ones.
REQUIRED_EXTENSIONS = {
    "ChatBot",                  # this fork's own extension — the whole point
    "BlueSpiceFoundation",      # everything else in settings.d depends on it
    "BlueSpiceExtendedSearch",  # wiki search
    "BlueSpicePrivacy",         # owns the consent step the login flow handles
    "BlueSpiceDiscovery",       # the default skin
    "SemanticMediaWiki",
    "PageForms",
    "Mermaid",                  # the converted docs use {{#mermaid:}}
    "SyntaxHighlight",
    "DynamicPageList3",         # the main page's "recently edited" list
    "Vector",                   # the skin whose 59 missing files were QA Bug 4
}

# A settings.d file that fails to load takes tens of extensions with it, so a
# floor catches that class of breakage without pinning an exact count.
MIN_EXTENSIONS = 150


@pytest.fixture(scope="session")
def indexable_namespaces(repo_root):
    source = repo_root.joinpath(*INGEST_SOURCE)
    assert source.is_file(), f"{source} is missing from the checkout"
    match = INDEXABLE_RE.search(source.read_text(encoding="utf-8"))
    assert match, (
        f"could not find INDEXABLE_NAMESPACES in {'/'.join(INGEST_SOURCE)}. If "
        f"it was renamed or moved, update INDEXABLE_RE here — this test exists "
        f"to prove the namespaces the ingester reads from actually exist."
    )
    return [int(n) for n in match.group(1).replace(" ", "").split(",") if n]


# ─── the globals themselves ─────────────────────────────────────────
def test_sitename_and_language(mw_globals, wiki, dotenv):
    """$wgSitename and $wgLanguageCode, three ways.

    docker-compose.yml threads MW_SITENAME/MW_LANG into the container, setup.sh
    passes them to install.php, and install.php writes them into
    LocalSettings.php. The API is where a user sees the result. All three must
    agree, or a `.env` edit silently did nothing.
    """
    g = mw_globals(["wgSitename", "wgLanguageCode"])
    general = wiki.siteinfo("general")["general"]

    assert g["wgSitename"] == general["sitename"], (
        f"$wgSitename is {g['wgSitename']!r} but the API reports "
        f"{general['sitename']!r}."
    )
    assert g["wgLanguageCode"] == general["lang"], (
        f"$wgLanguageCode is {g['wgLanguageCode']!r} but the API reports "
        f"{general['lang']!r}."
    )

    # The compose defaults, which .env may legitimately override.
    expected_name = dotenv.get("MW_SITENAME", "BlueSpice HDP")
    expected_lang = dotenv.get("MW_LANG", "de")
    assert g["wgSitename"] == expected_name, (
        f"$wgSitename is {g['wgSitename']!r}, but MW_SITENAME asks for "
        f"{expected_name!r}. install.php only reads it at first install, so a "
        f"later .env change does not reach an existing wiki."
    )
    assert g["wgLanguageCode"] == expected_lang, (
        f"$wgLanguageCode is {g['wgLanguageCode']!r}, but MW_LANG asks for "
        f"{expected_lang!r}."
    )


def test_server_and_script_path(mw_globals, wiki):
    """$wgServer and $wgScriptPath, cross-checked against the API.

    $wgServer being wrong is not cosmetic: MediaWiki redirects to canonical
    URLs built from it, so a wrong value makes every page load bounce somewhere
    unreachable. It is also why this whole tier runs from the host.
    """
    g = mw_globals(["wgServer", "wgScriptPath"])
    general = wiki.siteinfo("general")["general"]
    assert g["wgServer"] == general["server"]
    assert g["wgScriptPath"] == general["scriptpath"]
    assert g["wgScriptPath"] == "/w", (
        f"$wgScriptPath is {g['wgScriptPath']!r}. docker-compose.yml pins "
        f"MW_SCRIPT_PATH=/w and Apache is configured for it; changing one "
        f"without the other 404s the entire wiki."
    )


def test_meta_namespace_matches_settings_d(mw_globals, wiki):
    """$wgMetaNamespace, set by app/settings.d/020-DefaultSettings.php.

    setup.sh seeds Site:Nutzungsbedingungen and Site:Datenschutz by that
    literal prefix, and BlueSpice shows a warning banner on every page until
    both exist — so if this global moves, the seeded pages land in a namespace
    nobody looks at and the banner never goes away.
    """
    g = mw_globals(["wgMetaNamespace"])
    assert g["wgMetaNamespace"] == "Site"
    namespaces = wiki.siteinfo("namespaces")["namespaces"]
    ns4 = namespaces["4"]
    assert ns4["name"] == "Site", (
        f"$wgMetaNamespace is 'Site' but ns 4 is named {ns4['name']!r} in the "
        f"API — the two views of the same setting disagree."
    )


def test_sql_mode_has_no_only_full_group_by(mw_globals, mw_sql):
    """The Error 1055 fix, read back from the live database connection.

    `app/settings.d/050-Fixes.php` sets $wgSQLMode = 'STRICT_ALL_TABLES' to
    strip ONLY_FULL_GROUP_BY, which the dev image's DevelopmentSettings.php
    adds. With it in place, BlueSpiceUserSidebar's "recently visited pages"
    widget throws `Error 1055 ... isn't in GROUP BY` on *every logged-in page
    load*, including the main page.

    Asserting the global alone would not be enough — MediaWiki applies it per
    connection, so the value that matters is what the server reports for the
    session it actually opened.
    """
    g = mw_globals(["wgSQLMode"])
    assert g["wgSQLMode"] is not None, (
        "$wgSQLMode is unset, so the dev image's ONLY_FULL_GROUP_BY default is "
        "in force. See app/settings.d/050-Fixes.php."
    )
    assert "ONLY_FULL_GROUP_BY" not in g["wgSQLMode"], (
        f"$wgSQLMode is {g['wgSQLMode']!r}. ONLY_FULL_GROUP_BY breaks several "
        f"BlueSpice extensions on every logged-in page load."
    )

    out = mw_sql("SELECT @@SESSION.sql_mode AS m")
    match = re.search(r"\[m\] => (.*)", out)
    assert match, f"could not read the session sql_mode from sql.php:\n{out[:800]}"
    live = match.group(1).strip()
    assert "ONLY_FULL_GROUP_BY" not in live, (
        f"the live MariaDB session runs with sql_mode {live!r}. $wgSQLMode is "
        f"set correctly, so something re-applies it on the connection — note "
        f"that docker/mariadb/sql-mode.cnf alone cannot fix this, because "
        f"$wgSQLMode always wins."
    )


def test_search_backend_points_at_the_opensearch_service(mw_globals, compose):
    """ExtendedSearch is wired to the opensearch container, and reached it.

    `app/settings.d/050-Fixes.php` sets `bsgOverrideESBackendHost` and friends
    because BlueSpiceExtendedSearch's `extension.json` default is
    127.0.0.1:9200 — nothing at all inside the mediawiki container. Without the
    override every search throws `NoNodesAvailableException` and the Search
    Center renders no results, ever.

    Note the missing `wg` prefix, which is the trap: `BlueSpice\\Config` is a
    MultiConfig chain that consults a database-backed settings table *before*
    plain `$wgBsg*` globals, so an ordinary `$wgBsgESBackendHost` override is
    silently shadowed by the DB-seeded default. `bsgOverride*` is the only
    layer that wins. A test that read the `wg`-prefixed name would pass while
    search was broken.

    **What this does not assert: that search returns results.** The index is
    populated by background jobs, and `mediawiki-jobrunner` is not part of the
    T3 minimal profile — so on a freshly installed T3 wiki the queue holds
    ~500 pending jobs and `bluespice_wikipage` is empty. Full-text search
    coverage belongs to Wave 4's T4, against the full stack. What is asserted
    here is the half that fails deterministically and costs nothing: the
    config is right, and MediaWiki reached OpenSearch well enough for
    `initBackends.php` to create the index.
    """
    g = mw_globals(
        ["bsgOverrideESBackendHost", "bsgOverrideESBackendPort",
         "bsgOverrideESBackendTransport"]
    )
    assert g["bsgOverrideESBackendHost"] == "opensearch", (
        f"ExtendedSearch's backend host is {g['bsgOverrideESBackendHost']!r}. "
        f"The extension.json default is 127.0.0.1, which is nothing inside the "
        f"mediawiki container; see app/settings.d/050-Fixes.php."
    )
    assert str(g["bsgOverrideESBackendPort"]) == "9200"
    assert g["bsgOverrideESBackendTransport"] == "https"

    probe = compose(
        "exec", "-T", "opensearch", "sh", "-c",
        'curl -sk -u "admin:$OPENSEARCH_INITIAL_ADMIN_PASSWORD" '
        '"https://localhost:9200/_cat/indices?h=index"',
        timeout=180,
    )
    if probe.returncode != 0:
        pytest.skip("opensearch is not part of this stack, so there is no backend to check")
    assert "bluespice_wikipage" in probe.stdout, (
        "the bluespice_wikipage index does not exist. setup.sh step 4e runs "
        "initBackends.php to create it, so MediaWiki never successfully "
        "reached OpenSearch.\nIndices present:\n" + probe.stdout[:1000]
    )


def test_debug_logging_is_off(mw_globals, repo_root):
    """$wgDebugLogFile is empty and no debug log is accumulating.

    The dev image enables verbose debug logging with no rotation: 150 MB of CLI
    debug log within minutes of a fresh install, 2 GB on a longer-lived one.
    `050-Fixes.php` disables it. Both the setting and its consequence are
    asserted, because the setting is easy to restore and the consequence is
    what fills the disk.
    """
    g = mw_globals(["wgDebugLogFile", "wgDebugToolbar"])
    assert not g["wgDebugLogFile"], (
        f"$wgDebugLogFile is {g['wgDebugLogFile']!r}. Unrotated MediaWiki debug "
        f"logging fills the disk; see app/settings.d/050-Fixes.php."
    )
    assert not g["wgDebugToolbar"], "$wgDebugToolbar is on outside development."

    stray = sorted(p.name for p in (repo_root / "app" / "cache").glob("mw-debug*.log"))
    assert not stray, (
        f"debug logs are being written despite $wgDebugLogFile being empty: "
        f"{stray}. Something re-enabled it after settings.d ran."
    )


# ─── namespaces ─────────────────────────────────────────────────────
def test_every_indexable_namespace_exists(wiki, indexable_namespaces):
    """The namespaces the RAG ingester reads from are real.

    `ingest_hdp_wiki.py` selects pages `WHERE page_namespace IN
    INDEXABLE_NAMESPACES`. A namespace in that list that the wiki does not
    define is not an error anywhere — the query simply returns nothing and the
    index quietly shrinks. Wave 2 established the baseline: 32 pages across
    these namespaces produce 157 documents.
    """
    defined = {int(n) for n in wiki.siteinfo("namespaces")["namespaces"]}
    missing = [ns for ns in indexable_namespaces if ns not in defined]
    assert not missing, (
        f"INDEXABLE_NAMESPACES names namespace(s) {missing} that this wiki does "
        f"not define. Ingestion would silently index nothing from them."
    )


def test_help_namespace_is_canonically_help(wiki):
    """ns 12 is `Help` canonically and `Hilfe` locally.

    Every seeded doc page is written as `Help:...` by setup.sh while the wiki
    displays `Hilfe:...`. If the canonical name moved, those writes would
    create pages in the wrong place.
    """
    ns12 = wiki.siteinfo("namespaces")["namespaces"]["12"]
    assert ns12["canonical"] == "Help", (
        f"ns 12's canonical name is {ns12['canonical']!r}, not 'Help'. setup.sh "
        f"seeds the docs by that prefix."
    )
    assert ns12["name"] == "Hilfe", (
        f"ns 12 is displayed as {ns12['name']!r}; the wiki content language is "
        f"German, so this should be 'Hilfe'."
    )


def test_hdp_custom_namespaces_are_defined(wiki, indexable_namespaces):
    """The two HDP-specific namespaces the ingester targets.

    5000 and 5002 are configured through BlueSpice's NamespaceManager, i.e. in
    the database rather than in any file in this repo — so nothing in a code
    review would notice them disappearing. They are empty today by design; the
    assertion is that they exist for content to be filed into.
    """
    namespaces = wiki.siteinfo("namespaces")["namespaces"]
    for ns_id, expected in ((5000, "Ministerium"), (5002, "Projektträger")):
        if ns_id not in indexable_namespaces:
            continue
        assert str(ns_id) in namespaces, f"namespace {ns_id} is not defined"
        assert namespaces[str(ns_id)]["name"] == expected, (
            f"namespace {ns_id} is named "
            f"{namespaces[str(ns_id)]['name']!r}, not {expected!r}. These are "
            f"stored in the database by BlueSpice NamespaceManager, not in the "
            f"repo, so a rename leaves no trace in git."
        )


# ─── extensions ─────────────────────────────────────────────────────
def test_required_extensions_are_loaded(wiki):
    loaded = {e.get("name") for e in wiki.siteinfo("extensions")["extensions"]}
    missing = sorted(REQUIRED_EXTENSIONS - loaded)
    assert not missing, (
        f"{len(missing)} required extension(s) are not loaded: {missing}. "
        f"BlueSpice loads extensions from app/settings.d/*.php in alphanumeric "
        f"order, not from wfLoadExtension() in LocalSettings.php — a fatal in "
        f"one of those files drops everything after it."
    )


def test_extension_count_is_plausible(wiki):
    loaded = wiki.siteinfo("extensions")["extensions"]
    assert len(loaded) >= MIN_EXTENSIONS, (
        f"only {len(loaded)} extensions are loaded, below the floor of "
        f"{MIN_EXTENSIONS}. A settings.d file that fails partway takes every "
        f"extension after it with it, and the wiki still boots."
    )


def test_generator_is_the_expected_mediawiki_release(wiki):
    """MediaWiki 1.43.x — the release every patch in docker/patches/ targets."""
    general = wiki.siteinfo("general")["general"]
    assert general["generator"].startswith("MediaWiki 1.43"), (
        f"the wiki reports {general['generator']!r}. The vendored Vector skin "
        f"is pinned to upstream REL1_43 and the 21 patches in docker/patches/ "
        f"are written against that branch."
    )
