"""3.3 — the content the wiki ships with.

`docker/setup.sh` steps 4b–4d seed the wiki on first boot: the main page, the
Chatbot FAQ, two legal placeholders, and the whole `docs/wiki/` tree converted
to Help-namespace pages by `scripts/convert-docs.sh`. Every one of those
`edit.php` calls ends in `|| echo "WARNING: ... (non-fatal, continuing)"`, so
a failed seed does not fail setup.sh and does not show up in its failure
count. The wiki simply boots with red links.

That is the gap this file closes. The expected Help pages are derived from
`docker/mediawiki/wiki-docs/*.wiki` rather than hardcoded, so adding a doc page
without seeding it is caught automatically.
"""
import os

import pytest

# Pages that come from the *repo* — the seeded content plus everything
# BlueSpice's own installer creates. Excludes the user-owned namespaces below.
#
# 107, not 108, and the difference is the whole reason this constant is
# filtered. Earlier wave notes recorded both figures for the same wiki and
# treated it as a discrepancy; it is not. A total page count includes
# `Benutzer:Admin`, which the CreateUserPage extension creates the first time
# a user logs in *through the web UI* — so the total is 107 immediately after
# install, 108 once somebody looks at the wiki, and higher again per user.
#
# Asserting on the total therefore fails because a human opened a browser,
# which is not a defect in anything. Filtering the user-owned namespaces makes
# the number describe what this file is actually about: the content the repo
# ships.
EXPECTED_SEEDED_PAGES = 107

# ns 4 is the meta namespace, which app/settings.d/020-DefaultSettings.php
# names "Site" via $wgMetaNamespace.
NS_MAIN, NS_META, NS_HELP = 0, 4, 12

# Created on demand as users appear, by CreateUserPage (ns 2/3) and
# SimpleBlogPage (ns 502/503). Never seeded, never part of the baseline.
USER_OWNED_NAMESPACES = {2, 3, 502, 503}

# Pages seeded from a specific committed .wiki file, as (namespace, title).
SEEDED_SINGLETONS = [
    (NS_MAIN, "Hauptseite"),          # docker/mediawiki/hauptseite.wiki
    (NS_MAIN, "Chatbot-FAQ"),         # docker/mediawiki/chatbot-faq.wiki
    (NS_META, "Site:Nutzungsbedingungen"),
    (NS_META, "Site:Datenschutz"),
]

# Every content model this wiki legitimately stores.
#
# The Wave 3 brief expected "wikitext or NULL". That is wrong, and asserting it
# would have failed on a healthy wiki: a live stack holds eight models, seven
# of them non-wikitext and all of them correct — MediaWiki: namespace JSON and
# CSS pages, SMW schemas, PDFCreator and notification templates, a workflow
# trigger definition and the blog root. What "no corrupt pages" can honestly
# mean is that no page carries a model outside this set, so an unknown model —
# which is what a botched import or a half-removed extension leaves behind —
# is what fails.
KNOWN_CONTENT_MODELS = {
    "wikitext",
    "json",
    "sanitized-css",
    "smw/schema",
    "mail_template",
    "pdfcreator_template",
    "workflow-triggers",
    "blog_root",
}


def _expected_help_pages(repo_root):
    """Help page titles derived from the committed wiki-docs filenames.

    `convert-docs.sh` encodes the MediaWiki subpage separator '/' as '__' in
    filenames, because a filename cannot contain '/'. setup.sh reverses it when
    calling edit.php, so the same transform has to happen here.
    """
    wiki_docs = repo_root / "docker" / "mediawiki" / "wiki-docs"
    assert wiki_docs.is_dir(), f"{wiki_docs} is missing from the checkout"
    titles = sorted(p.stem.replace("__", "/") for p in wiki_docs.glob("*.wiki"))
    assert titles, f"{wiki_docs} contains no .wiki files to seed"
    return titles


def _missing_titles(wiki, titles):
    """Which of `titles` the wiki does not have.

    Queried in one round trip. The API normalises the canonical `Help:` prefix
    to the localised `Hilfe:` itself, so the committed filenames can be used
    verbatim without the test knowing the content language.
    """
    missing = []
    for chunk in (titles[i : i + 40] for i in range(0, len(titles), 40)):
        data = wiki.api(action="query", titles="|".join(chunk))
        for page in data.get("query", {}).get("pages", []):
            if page.get("missing") or page.get("invalid"):
                missing.append(page.get("title"))
    return missing


@pytest.fixture(scope="session")
def expected_seeded_pages():
    return int(os.environ.get("HDP_EXPECTED_PAGE_COUNT", EXPECTED_SEEDED_PAGES))


def _pages_by_namespace(wiki):
    """Every page title on the wiki, grouped by namespace id."""
    # Negative ids are MediaWiki's virtual namespaces (-1 Special, -2 Media).
    # They hold no rows in the page table and `list=allpages` rejects them
    # outright with badvalue rather than returning an empty list.
    namespaces = [int(n) for n in wiki.siteinfo("namespaces")["namespaces"] if int(n) >= 0]
    return {ns: wiki.all_pages(ns) for ns in namespaces}


def test_main_page_exists_and_is_the_configured_main_page(wiki):
    general = wiki.siteinfo("general")["general"]
    assert general["mainpage"] == "Hauptseite", (
        f"$wgSitename's main page is {general['mainpage']!r}; the seeded page "
        f"docker/mediawiki/hauptseite.wiki targets 'Hauptseite', so the two "
        f"have drifted apart and the wiki's front door is a red link."
    )
    assert not _missing_titles(wiki, ["Hauptseite"]), (
        "Hauptseite does not exist. setup.sh step 4b seeds it from "
        "/hauptseite.wiki and swallows the failure with a WARNING, so check "
        "the setup log for 'main page population failed'."
    )


@pytest.mark.parametrize("namespace,title", SEEDED_SINGLETONS)
def test_seeded_page_exists(wiki, namespace, title):
    missing = _missing_titles(wiki, [title])
    assert not missing, (
        f"{title} (ns {namespace}) was not seeded. Every seeding step in "
        f"setup.sh reports failure as a non-fatal WARNING and continues, so "
        f"this will not have shown up in its failure count."
    )


def test_every_committed_help_doc_was_seeded(wiki, repo_root):
    """The docs/wiki/ tree really made it into the Help namespace.

    Derived from the committed files, so a doc page added to
    docker/mediawiki/wiki-docs/ without a working seed fails here rather than
    silently shipping a red link from the main page.
    """
    expected = _expected_help_pages(repo_root)
    missing = _missing_titles(wiki, expected)
    assert not missing, (
        f"{len(missing)} of {len(expected)} committed Help pages are not in "
        f"the wiki: {missing}. setup.sh step 4d seeds these from /wiki-docs "
        f"and is guarded by cache/.wiki-docs-populated, so a stack that failed "
        f"once will not retry on restart."
    )


def test_help_namespace_is_populated(wiki, repo_root):
    """ns 12 holds the seeded docs plus BlueSpice's own help pages."""
    titles = wiki.all_pages(NS_HELP)
    expected = _expected_help_pages(repo_root)
    assert len(titles) >= len(expected), (
        f"ns {NS_HELP} has {len(titles)} pages but {len(expected)} were seeded "
        f"from docker/mediawiki/wiki-docs/ alone."
    )


def test_main_namespace_holds_the_two_content_pages(wiki):
    titles = sorted(wiki.all_pages(NS_MAIN))
    assert titles == ["Chatbot-FAQ", "Hauptseite"], (
        f"ns 0 holds {titles}, not the two seeded pages. Note that MediaWiki's "
        f"`list=search` defaults to ns 0 — content on this wiki lives in ns 12."
    )


def test_seeded_page_count_matches_the_baseline(wiki, expected_seeded_pages):
    """The repo's own content, counted without the user-owned namespaces.

    See EXPECTED_SEEDED_PAGES for why the total is the wrong number to assert.
    """
    by_ns = _pages_by_namespace(wiki)
    seeded = {ns: t for ns, t in by_ns.items() if ns not in USER_OWNED_NAMESPACES}
    count = sum(len(t) for t in seeded.values())
    assert count == expected_seeded_pages, (
        f"the wiki has {count} seeded pages, not the {expected_seeded_pages} a "
        f"fresh install produces. More is usually a seed running twice; fewer "
        f"is a seed that failed. Per namespace: "
        f"{ {ns: len(t) for ns, t in sorted(seeded.items()) if t} }.\n"
        f"Override with HDP_EXPECTED_PAGE_COUNT for a one-off, or update the "
        f"constant in the commit that changes the seed content."
    )


def test_no_page_has_an_unknown_content_model(wiki):
    """Nothing is stored under a content model this wiki does not handle.

    A page whose model no installed extension provides renders as an error and
    is invisible to search and to ingestion — the concrete meaning of
    "no corrupt pages".
    """
    namespaces = [int(n) for n in wiki.siteinfo("namespaces")["namespaces"] if int(n) >= 0]
    found = {}
    for ns in namespaces:
        cont = {}
        while True:
            params = {
                "action": "query",
                "generator": "allpages",
                "gapnamespace": str(ns),
                "gaplimit": "max",
                "prop": "info",
            }
            params.update(cont)
            data = wiki.api(**params)
            for page in data.get("query", {}).get("pages", []):
                found.setdefault(page.get("contentmodel"), []).append(page["title"])
            cont = data.get("continue")
            if not cont:
                break

    assert found, "the wiki reports no pages at all in any namespace"
    unknown = {
        model: titles[:5]
        for model, titles in found.items()
        if model not in KNOWN_CONTENT_MODELS
    }
    assert not unknown, (
        f"pages stored under unrecognised content model(s): {unknown}. If an "
        f"extension legitimately added one, add it to KNOWN_CONTENT_MODELS "
        f"here; otherwise these pages cannot render."
    )
