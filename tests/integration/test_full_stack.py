"""4.1 + 4.2 — the full seven-container stack, and anonymous access.

The headline assertion of Wave 4: `setup.sh` runs end to end on a clean stack
and all seven containers reach `healthy`. Everything else in the smoke tier
(search, chatbot) is a statement about one leg; this file is the statement
about the stack existing at all.

**Seven containers, and `pdf-generator` is not one of them.** The Wave 4 brief
lists mariadb, mediawiki, opensearch, haystack, chatbot-proxy, pdf-generator
and jobrunner. docker-compose.yml has no `pdf-generator` service — that name
refers to the haystack container's *second* port (`HDP_PDF_PORT`, 1417), where
`docker/haystack/hdp_api_server.py` serves /health, /ready and the RAG query
API alongside hayhooks on 1416. The service the brief's list actually omits is
`mediawiki-web`. See FULL_STACK_SERVICES in conftest.py.

**`mediawiki-web` unhealthy before `setup.sh` is not a flake.** There is no
LocalSettings.php on a fresh volume, so `/w/` 500s and the healthcheck fails
correctly. It goes green the moment setup.sh installs the wiki. Reproduced on
every clean box since Wave 2; the assertions here run *after* setup.sh, which
is the only point at which "all seven healthy" is a meaningful claim.
"""
import re

import pytest

pytestmark = pytest.mark.smoke

# MediaWiki puts `wgUserName` in the RLCONF blob of every HTML response: a
# string for an authenticated request, `null` for an anonymous one. The
# authenticated tier asserts the Admin form of the same marker; here it is the
# control in the other direction.
ANON_USERNAME_RE = re.compile(r'"wgUserName":\s*("(?:[^"\\]|\\.)*"|null)')

# The login form's actual input, rather than the word "login" appearing
# somewhere in the chrome — every page on this wiki links to Special:UserLogin,
# so a substring search for the title passes on any page at all.
LOGIN_FORM_MARKERS = ('name="wpName"', 'name="wpPassword"')


def test_all_seven_containers_are_healthy(full_stack, full_stack_services):
    """Every service in docker-compose.yml is running, and healthy where it can be.

    All seven declare a healthcheck, so `running` on its own is not enough:
    haystack in particular answers on its port for ~90 seconds before the
    pipeline is deployed, and chatbot-proxy starts instantly whether or not
    haystack behind it works. `healthy` is the state that means the container
    answered its own definition of working.
    """
    unhealthy = {
        service: info
        for service, info in full_stack.items()
        if service in full_stack_services
        and (info["state"] != "running" or info["health"] != "healthy")
    }
    assert not unhealthy, (
        "not every container reached healthy:\n"
        + "\n".join(
            f"  {name:<22} state={info['state']!r} health={info['health']!r}"
            for name, info in sorted(unhealthy.items())
        )
        + "\n\nAll seven have a healthcheck, so an empty health field means the "
        "container is running but its check has not passed yet. Note that "
        "mediawiki-web is legitimately unhealthy *before* setup.sh (no "
        "LocalSettings.php exists, so /w/ 500s) — this assertion runs after."
    )


def test_the_stack_is_exactly_seven_services(full_stack, full_stack_services):
    """No more and no fewer than the seven services under test.

    A guard against the list in conftest.py drifting away from
    docker-compose.yml: adding an eighth service without adding it here would
    leave it permanently unasserted, which is how the jobrunner went uncovered
    through all of Wave 3.
    """
    assert len(full_stack_services) == 7, full_stack_services
    extra = sorted(set(full_stack) - set(full_stack_services))
    assert not extra, (
        f"the stack is running services this tier does not assert on: {extra}. "
        f"Add them to FULL_STACK_SERVICES in tests/integration/conftest.py, or "
        f"they will never be smoke-tested."
    )


# ─── 4.2 anonymous access ───────────────────────────────────────────
def test_anonymous_wiki_root_is_served(anon):
    """`GET /w/` answers 200 for a visitor with no session.

    Not "the main page renders": `$wgGroupPermissions` on this wiki may or may
    not let anonymous users read content, and both outcomes are legitimate
    deployments. What is not legitimate is a 500, a 502, or a redirect loop —
    which is what a broken skin, a missing LocalSettings.php or a dead
    PHP-FPM produce, and all three are silent to an authenticated test that
    logs in first.
    """
    resp = anon.fetch("/")
    assert resp.status == 200, (
        f"GET {anon.base}/ returned HTTP {resp.status}. Anonymous visitors get "
        f"either the main page or the login page; either is a 200."
    )
    assert resp.body, "GET /w/ returned an empty body with status 200"


def test_anonymous_login_page_renders_its_form(anon):
    """Special:UserLogin returns 200 *and* contains a usable form.

    The status code alone proves nothing here — Wave 3 established that this
    wiki answers `Special:Preferences` with a healthy 200 login prompt when
    logged out. So the assertion is on the form's own input names: if
    `wpName`/`wpPassword` are absent, nobody can log in regardless of what the
    status line says.
    """
    resp = anon.fetch("/index.php/Special:UserLogin")
    assert resp.status == 200, (
        f"Special:UserLogin returned HTTP {resp.status}; nobody can log in."
    )
    body = resp.text
    missing = [m for m in LOGIN_FORM_MARKERS if m not in body]
    assert not missing, (
        f"the login page rendered ({len(resp.body)} bytes) but is missing "
        f"{missing}. The page loads and the form does not."
    )


def test_no_authenticated_content_leaks_to_anonymous_users(anon):
    """An anonymous session is anonymous on every page it can reach.

    The concrete risk is a caching layer serving a logged-in user's rendered
    page to the next visitor, which shows up as another user's name in the
    RLCONF blob. `wgUserName` is `null` for an anonymous request and a string
    for an authenticated one, so the check is exact rather than a substring
    hunt for "Admin" (which appears legitimately in page *content*, e.g. a
    revision history byline).
    """
    for path in ("/", "/index.php/Special:UserLogin", "/index.php/Hauptseite"):
        resp = anon.fetch(path)
        if resp.status != 200:
            continue
        body = resp.text
        match = ANON_USERNAME_RE.search(body)
        if match is None:
            # Not every response carries RLCONF (a redirect body, an error
            # page). Nothing to assert, and nothing leaked either.
            continue
        assert match.group(1) == "null", (
            f"{path} was served to an anonymous client with "
            f"wgUserName={match.group(1)}. Somebody else's session is being "
            f"handed out — check for a cache in front of MediaWiki."
        )


def test_anonymous_api_is_not_a_way_around_the_login(anon):
    """The read API refuses an anonymous caller, as this wiki is configured.

    Wave 3 found that `action=query&meta=siteinfo` returns `readapidenied`
    here, and every test in the tier depends on that being why they log in.
    Pinned as a property rather than a footnote: if the API ever opens up, this
    goes red and somebody decides deliberately, instead of the integration
    suite quietly starting to test an anonymous wiki.
    """
    with pytest.raises(RuntimeError, match="readapidenied"):
        anon.api(action="query", meta="siteinfo", siprop="general")
