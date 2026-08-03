"""The release-watch feed parsers, against captured copies of the real feeds.

The failure this guards against is not a crash — it is a watcher that quietly
reports "up to date" forever because a feed changed shape, or one that cries
wolf on a release candidate. Both look identical from the outside, which is why
the parsing is pure and tested here rather than only exercised by the weekly
job.

Fixtures in tests/unit/fixtures/release_watch/ are real responses, captured
2026-08-03. No network.
"""
import json
import os

import pytest
import release_watch as rw

FIXTURES = os.path.join(os.path.dirname(os.path.abspath(__file__)), "fixtures", "release_watch")


def fixture(name):
    return open(os.path.join(FIXTURES, name), encoding="utf-8").read()


# ─── Version handling ───────────────────────────────────────────────

@pytest.mark.parametrize("text, expected", [
    ("1.43.5", (1, 43, 5)),
    ("5.1", (5, 1)),
    ("1.43.10", (1, 43, 10)),
    ("5.2.0-alpha", None),
    ("REL1_43", None),
    ("", None),
    (None, None),
])
def test_parse_version(text, expected):
    assert rw.parse_version(text) == expected


def test_ordering_is_numeric_not_lexical():
    """1.43.10 > 1.43.9, which string comparison gets backwards."""
    assert rw.parse_version("1.43.10") > rw.parse_version("1.43.9")


# ─── MediaWiki ──────────────────────────────────────────────────────

def test_reads_the_releases_in_our_branch():
    releases = rw.parse_mw_releases(fixture("mw-branch-index.html"))
    assert rw.parse_version("1.43.9") in releases
    assert rw.parse_version("1.43.0") in releases


def test_release_candidates_and_signatures_are_not_releases():
    """The same directory lists .tar.gz.sig, .patch.gz and -rc.0 tarballs.

    Matching any of those would announce a signature file or a release
    candidate as an upstream release — the first false alarm is what teaches
    people to close this issue unread.
    """
    html = fixture("mw-branch-index.html")
    assert "mediawiki-1.43.0-rc.0.tar.gz" in html and "mediawiki-1.43.1.patch.gz" in html
    releases = rw.parse_mw_releases(html)
    assert all(len(v) == 3 for v in releases)
    assert rw.parse_version("1.43.1") in releases  # the patch.gz did not hide the release


def test_reads_the_branch_list():
    branches = rw.parse_mw_branches(fixture("mw-index.html"))
    assert rw.parse_version("1.43") in branches
    assert max(branches) >= rw.parse_version("1.46")


# ─── BlueSpice ──────────────────────────────────────────────────────

def test_reads_stable_bluespice_versions():
    payload = json.loads(fixture("bluespice-p2.json"))
    versions = rw.parse_bluespice_versions(payload)
    assert rw.parse_version("5.1.9") in versions
    assert rw.parse_version("5.2.5") in versions


def test_prereleases_are_excluded():
    """5.2.0-alpha is published to the same repository.

    A wiki serving a federal ministry does not get upgraded to an alpha
    because a watcher could not tell the difference.
    """
    payload = json.loads(fixture("bluespice-p2.json"))
    raw = [e["version"] for e in payload["packages"]["bluespice/foundation"]]
    assert any("-" in v for v in raw), "fixture no longer contains a pre-release"
    assert all(len(v) == 3 for v in rw.parse_bluespice_versions(payload))


def test_an_unexpected_payload_yields_nothing_rather_than_crashing():
    assert rw.parse_bluespice_versions({}) == []
    assert rw.parse_bluespice_versions({"packages": {}}) == []
    assert rw.parse_bluespice_versions({"packages": {rw.BS_PACKAGE: ["nonsense"]}}) == []


# ─── The comparison ─────────────────────────────────────────────────

def _fetcher():
    def fetch(url):
        if url.endswith("/1.43/"):
            return fixture("mw-branch-index.html")
        if url == rw.MW_INDEX:
            return fixture("mw-index.html")
        if url == rw.BS_METADATA:
            return fixture("bluespice-p2.json")
        raise AssertionError(f"unexpected url {url}")
    return fetch


def test_the_committed_versions_are_behind_and_it_says_so():
    """As of the captured feeds this fork is four MediaWiki patch releases behind.

    That is a fact about 2026-08-03, and it is exactly the finding the job
    exists to surface — if this assertion ever fails because the fixtures were
    refreshed after an upgrade, the fix is to update the expected versions,
    not to soften the check.
    """
    report = rw.check("1.43.5", "5.1.4", fetcher=_fetcher())
    kinds = {f["what"]: f for f in report["findings"]}
    assert kinds["mediawiki-patch"]["latest"] == "1.43.9"
    assert kinds["mediawiki-patch"]["severity"] == "act"
    assert kinds["bluespice-patch"]["latest"] == "5.1.9"
    assert kinds["mediawiki-branch"]["severity"] == "plan"
    assert kinds["bluespice-minor"]["latest"] == "5.2.5"


def test_being_current_reports_nothing():
    """The whole point of the job is that this is the normal Monday result."""
    def fetch(url):
        if url == rw.MW_INDEX:
            return '<a href="1.43/">1.43/</a>'
        if url.endswith("/1.43/"):
            return "mediawiki-1.43.9.tar.gz"
        if url == rw.BS_METADATA:
            return json.dumps({"packages": {rw.BS_PACKAGE: [{"version": "5.1.9"}]}})
        raise AssertionError(f"unexpected url {url}")
    report = rw.check("1.43.9", "5.1.9", fetcher=fetch)
    assert report["findings"] == []


def test_a_dead_feed_raises_instead_of_reporting_up_to_date():
    """The one result this job must never give: silence read as good news."""
    def broken(url):
        raise rw.FeedError(f"{url}: nope")
    with pytest.raises(rw.FeedError):
        rw.check("1.43.5", "5.1.4", fetcher=broken)


def test_an_unreadable_declared_version_is_an_error():
    with pytest.raises(rw.FeedError):
        rw.check("REL1_43", "5.1.4", fetcher=_fetcher())


# ─── The issue it files ─────────────────────────────────────────────

def test_the_issue_title_carries_the_versions():
    """Dedupe is by exact title, so the title has to move when the finding does."""
    report = rw.check("1.43.5", "5.1.4", fetcher=_fetcher())
    first = rw.issue_payload(report)["title"]
    assert "1.43.9" in first and "5.1.9" in first
    assert rw.issue_payload(rw.check("1.43.9", "5.1.9", fetcher=_fetcher()))["title"] != first


def test_the_issue_body_points_at_the_patch_report_first():
    report = rw.check("1.43.5", "5.1.4", fetcher=_fetcher())
    body = rw.issue_payload(report)["body"]
    assert "verify-patches.sh --upgrade-report" in body
    assert "docs/dev/upgrade-runbook.md" in body
