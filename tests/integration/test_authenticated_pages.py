"""3.1 — authenticated page loads. The headline assertion of Wave 3.

QA Bug 4 (docs/QA-REPORT.md): every authenticated page returned HTTP 500,
because the vendored Vector skin was a strict subset of upstream REL1_43 and
was missing 59 files under `includes/`. `Special:Preferences` was the visible
casualty. Wave 0 restored all 59 files (`3592d8dbe`) and the wiki has been
green ever since — but nothing in the repo *proved* it stayed green. Both Wave
1 and Wave 2 verified it by hand, on a box, with a throwaway script that lived
in /root. This file is that check, committed.

The subtlety worth reading before editing anything here: **a 200 is not enough
on its own.** Logged out, this wiki answers `Special:Preferences` with a
perfectly healthy HTTP 200 "you must log in" page. So a test that only asserts
the status code passes while proving nothing — and would have passed during
the whole period the bug was live if the session had quietly failed to
authenticate. Every page assertion below therefore also asserts that MediaWiki
rendered the page *for Admin*, via the `wgUserName` value it embeds in the
ResourceLoader config blob of every HTML response.
"""
import re

import pytest

# The four pages the Wave 3 brief names, plus the two the hand-run smoke
# script covered. Special:Preferences is first because it is the one that
# broke: a 418 KB form that touches more of the skin than anything else.
AUTHENTICATED_PAGES = [
    "/index.php/Special:Preferences",
    "/index.php/Special:SpecialPages",
    "/index.php/Hauptseite",
    "/index.php/Special:Version",
    "/index.php/Special:RecentChanges",
    "/",
]

# MediaWiki emits this into the RLCONF blob of every HTML response. It is the
# cheapest available proof that the response was rendered for a logged-in user
# rather than for an anonymous one.
LOGGED_IN_MARKER = '"wgUserName":"Admin"'

# Substrings that mean PHP or MediaWiki blew up while producing the page.
# `display_errors` is set to 0 by setup.sh, so in the normal case a fatal
# surfaces as a 500 with an empty body — but a fatal inside a lazily-included
# component can still be rendered into an otherwise-200 page, which is the
# quiet failure this catches.
FATAL_MARKERS = [
    "Fatal error",
    "Uncaught Exception",
    "MWExceptionRenderer",
    "Internal error",
    "[Fatal Error]",
]

# Special:Preferences was 418 KB on the Wave 2 clean box and 428 KB in Wave 1.
# The floor is deliberately far below both: this is a "the form rendered at
# all" assertion, not a byte-for-byte one. The broken version was a 500 with
# no body, so any realistic floor separates the two.
PREFERENCES_MIN_BYTES = 100 * 1024


def assert_healthy(response, label):
    assert response.status == 200, (
        f"{label} returned HTTP {response.status} for a logged-in user. "
        f"This is the QA Bug 4 signature — check that the vendored Vector "
        f"skin still matches upstream REL1_43 (see docs/QA-REPORT.md and "
        f"commit 3592d8dbe).\nFirst 500 bytes: {response.text[:500]!r}"
    )
    assert LOGGED_IN_MARKER in response.text, (
        f"{label} returned 200 but MediaWiki did not render it for Admin "
        f"({LOGGED_IN_MARKER} is absent). The session is not authenticated, so "
        f"this page's 200 proves nothing — logged out, MediaWiki answers "
        f"restricted pages with a healthy 200 login prompt."
    )
    found = [m for m in FATAL_MARKERS if m in response.text]
    assert not found, f"{label} rendered with PHP/MediaWiki error markers: {found}"


@pytest.mark.parametrize("path", AUTHENTICATED_PAGES)
def test_authenticated_page_loads(wiki, path):
    assert_healthy(wiki.fetch(path), path)


def test_preferences_renders_the_full_form(wiki):
    """Special:Preferences specifically — size and form structure.

    The bug produced a 500. A regression that produced a *stub* page would
    still be a regression, so assert the form is really there.
    """
    r = wiki.fetch("/index.php/Special:Preferences")
    assert_healthy(r, "Special:Preferences")
    assert len(r.body) >= PREFERENCES_MIN_BYTES, (
        f"Special:Preferences is only {r.kib} KiB. It was 418 KiB on the Wave 2 "
        f"clean box; a page this small is not the full preferences form."
    )
    assert 'id="mw-prefs-form"' in r.text or "mw-htmlform" in r.text, (
        "Special:Preferences returned a large authenticated page with no "
        "preferences form in it."
    )


def test_help_namespace_content_page_loads(wiki):
    """A real content page in ns 12, chosen from what the wiki actually has.

    Not hardcoded: the Help pages are generated from docs/wiki/ by
    scripts/convert-docs.sh, so a fixed title turns a rename into a failure of
    this test rather than of the test that owns page names
    (test_seeded_pages.py).
    """
    titles = wiki.all_pages(12)
    assert titles, "ns 12 (Help) has no pages — see test_seeded_pages.py"
    title = sorted(titles)[0]
    r = wiki.fetch("/index.php/" + title.replace(" ", "_"))
    assert_healthy(r, f"ns 12 page {title!r}")


def test_anonymous_preferences_is_not_the_authenticated_page(anon):
    """The control for the whole file.

    If this fails, the `wgUserName` marker above no longer distinguishes a
    logged-in render from an anonymous one, and every assertion in this module
    has quietly stopped testing authentication.
    """
    r = anon.fetch("/index.php/Special:Preferences")
    assert LOGGED_IN_MARKER not in r.text, (
        "an anonymous request rendered Special:Preferences as Admin — the "
        "cookie jar is leaking between clients, or the wiki grants anonymous "
        "access to user preferences."
    )


def test_php_error_logging_is_enabled(mw_exec):
    """Guard: PHP diagnostics must actually reach a log somewhere.

    This test exists because the one below it was vacuous when it was written.
    `docker/wiki/www.conf` shipped `log_errors = off`, so a genuine PHP fatal
    produced an HTTP 500 with a zero-byte body and not a single line in any
    log, in any container, on disk or on stdout — proven by requesting a page
    calling an undefined function. A "no fatals in the log" assertion against
    a log that can never contain one passes forever and means nothing.

    So: assert the configuration that makes the next test load-bearing, read
    out of the *running* container rather than the repo, because www.conf is a
    bind mount and a stack started before the fix keeps the old pool config
    until it is recreated.
    """
    proc = mw_exec("cat", "/etc/php/8.3/fpm/pool.d/www.conf", timeout=120)
    assert proc.returncode == 0, f"could not read the FPM pool config: {proc.stderr}"
    conf = proc.stdout
    assert re.search(r"log_errors\]?\s*=\s*on", conf, re.IGNORECASE), (
        "FPM has log_errors off, so PHP fatals are written nowhere and "
        "test_no_php_fatals_in_container_logs below cannot fail. Fix "
        "docker/wiki/www.conf, then recreate the container — restarting is "
        "not enough for a bind-mounted pool config to be re-read."
    )
    assert "/proc/self/fd/2" in conf, (
        "FPM's error_log does not point at the worker's stderr, so fatals do "
        "not reach `docker compose logs`. See docker/wiki/www.conf."
    )


def test_no_php_fatals_in_container_logs(compose):
    """No PHP fatal anywhere in the wiki containers' output.

    Deliberately whole-log rather than a before/after window: a fatal from the
    install itself is as much a defect as one from a page load, and windowing
    would hide it.

    Only *fatals* match. The three messages this stack emits on every page load
    are a deprecation, a notice and a warning, all documented upstream issues,
    and `cache/mw-dberror.log` holds transient "Table doesn't exist" entries
    from the install window. None of them is a fatal, so none needs an
    exclusion here — and none should be added without reading
    test_php_error_logging_is_enabled first.
    """
    pattern = re.compile(
        r"PHP Fatal error|Fatal error:|Allowed memory size of|"
        r"Uncaught Error|Uncaught Exception|Uncaught TypeError",
        re.IGNORECASE,
    )
    offenders = {}
    for service in ("mediawiki", "mediawiki-web", "mediawiki-jobrunner"):
        proc = compose("logs", "--no-color", service, timeout=180)
        if proc.returncode != 0:
            # A service that is not part of the stack under test (the T3
            # minimal profile omits the jobrunner) is not a failure.
            continue
        hits = [ln for ln in proc.stdout.splitlines() if pattern.search(ln)]
        if hits:
            offenders[service] = hits[:20]
    assert not offenders, "PHP fatals in container logs:\n" + "\n".join(
        f"  [{svc}] {line}" for svc, lines in offenders.items() for line in lines
    )
