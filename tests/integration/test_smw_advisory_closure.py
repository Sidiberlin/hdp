"""DEPS-02: the live-request shape of the six reflected/stored XSS and the
one open-redirect backported against vendored SemanticMediaWiki 6.0.1 (see
docker/ci/composer-audit-baseline.json's mediawiki/semantic-media-wiki entry
and docs/dev/patches.md "The SMW set").

**Authored from source, not from a live capture.** The ratified plan
(.planning/features/deps02-smw-revendor/PLAN.md §9.1) expected the
orchestrator's O1 slot to hand this file a captured pre-fix request/response
pair to derive the payload and assertion shape from, so the implementer would
not have to invent one. That capture did not reach this implementer session
— this session has no live-infrastructure access at all (orchestrator-owned).
Every URL and parameter name below is instead traced directly from the
vulnerable/patched PHP itself (`ParametersProcessor::process()` for the
Special:Ask `p[...]`/`q`/`po`/`debug` wiring, `SpecialSearchByProperty`,
`SpecialURIResolver`, `SpecialFacetedSearch`'s `checkRequest()` checksum
gate), which is at least as authoritative as a capture but has not been
exercised against a running wiki. **This file has not been run.** Treat a
first run as a shakedown, not a rubber stamp — the request shapes here may
need adjusting once O2's live pass happens.

Unmarked (no `pytestmark`), so it runs in T3 like the rest of this
directory: it needs the wiki and `mediawiki-web`, nothing else (the SMW
special pages under test are HTML page views, not job-queue or search-index
dependent — see test_search.py's docstring for the contrast).

Each advisory gets one positive assertion (patched behaviour) plus the
negative control the plan's AC4 asks for: reverting the corresponding
`docker/patches/smw-*.patch` (e.g. `patch -R -p0 -d app <
docker/patches/smw-ask-sep-xss.patch` from the repo root, then
`docker compose exec mediawiki php maintenance/run.php
DumpRenderedHtml.php` is unnecessary — the bind mount is live, see
docs/dev/CLAUDE.md "Bind mounts are live") before re-running this file must
turn every assertion below red; re-applying it must turn them green again.
That round trip is what proves these are real regression tests and not
tautologies — see tests/unit/test_smw_xss_mitigations.py for the same
proof already carried out mechanically (and passing) at the unit tier,
against the committed source rather than a live render.

GHSA-jr78-w6w5-m8f8 (`action=smwtask`) is deliberately absent — carried,
not mitigated, here; its anonymous-refusal assertion belongs to the ChatBot
Sibling Handler Sweep (Phase 7, D6/O3), reusing the anon->403 machinery
SEC-06 builds there.
"""
import urllib.parse
import zlib

import pytest

XSS_MARKER = "smwdeps02xss"
XSS_PAYLOAD = f'"><img src=x onerror=alert(String.fromCharCode(88,83,83))data-{XSS_MARKER}=1>'


def _ask(wiki, **params):
    """GET Special:Ask with SMW's own p[...]/q/po/debug query-string wiring
    (SMW\\MediaWiki\\Specials\\Ask\\ParametersProcessor::process()) rather than
    MediaWiki's ordinary title-path convention, since #ask parameters (sep,
    headers, mainlabel, format, ...) are not plain query-string keys — they
    are nested under `p[...]` unless the request also carries a bare `p`
    infolink string.
    """
    qp = {"title": "Special:Ask", "q": params.pop("q", "")}
    if "po" in params:
        qp["po"] = params.pop("po")
    if "debug" in params:
        qp["debug"] = params.pop("debug")
    for key, value in params.items():
        qp[f"p[{key}]"] = value
    return wiki.fetch("/index.php?" + urllib.parse.urlencode(qp))


def _assert_payload_not_reflected_raw(resp, label):
    assert resp.status == 200, f"{label}: Special:Ask answered HTTP {resp.status}"
    assert "onerror=alert" not in resp.text, (
        f"{label}: the crafted onerror handler appears verbatim in the "
        f"response body — the payload was not escaped.\n"
        f"URL: {resp.url}"
    )
    # The escaped form must be present, not merely "the raw form is absent"
    # (which a truncated or errored response would also satisfy).
    assert (
        "&lt;img" in resp.text or "&#60;img" in resp.text or "onerror" not in resp.text
    ), f"{label}: expected an HTML-escaped payload somewhere in the response.\nURL: {resp.url}"


# ─── CVE-2026-77607 — Special:Ask table `sep` XSS ─────────────────────

def test_ask_sep_parameter_is_escaped(wiki):
    resp = _ask(
        wiki,
        q="[[Text::+]]",
        po="?Text",
        format="table",
        sep=XSS_PAYLOAD,
        limit="1",
    )
    _assert_payload_not_reflected_raw(resp, "sep (CVE-2026-77607)")


def test_ask_sep_br_allowlist_still_renders_a_real_linebreak(wiki):
    """The fix's one deliberate exception: <br> must still work as a
    separator, or the patch traded an XSS for a broken feature.
    """
    resp = _ask(wiki, q="[[Text::+]]", po="?Text", format="table", sep="<br>", limit="2")
    assert resp.status == 200
    assert "<br>" in resp.text or "<br/>" in resp.text or "<br />" in resp.text


# ─── CVE-2026-77606 — Special:Ask plain-header (mainlabel) XSS ────────

def test_ask_plain_header_mainlabel_is_escaped(wiki):
    resp = _ask(
        wiki,
        q="[[Text::+]]",
        po="?Text",
        format="table",
        headers="plain",
        mainlabel=XSS_PAYLOAD,
        limit="1",
    )
    _assert_payload_not_reflected_raw(resp, "mainlabel/headers=plain (CVE-2026-77606)")


# ─── CVE-2026-77608 — Special:SearchByProperty error-message XSS ──────

def test_searchbyproperty_invalid_property_error_is_escaped(wiki):
    resp = wiki.fetch(
        "/index.php?" + urllib.parse.urlencode({
            "title": "Special:SearchByProperty",
            "property": XSS_PAYLOAD,
        })
    )
    assert resp.status == 200, f"Special:SearchByProperty answered HTTP {resp.status}"
    assert "onerror=alert" not in resp.text, (
        "the invalid-property error message reflects the crafted payload "
        f"unescaped.\nURL: {resp.url}"
    )


# ─── CVE-2026-77609 — Special:URIResolver open redirect ───────────────

def test_uriresolver_rejects_an_interwiki_off_host_target(wiki):
    """The advisory's own example: an mw: interwiki prefix would otherwise
    303-redirect to mediawiki.org. wgServer here is localhost:8080 — no
    interwiki entry on this wiki should resolve off that host.
    """
    resp = wiki.fetch("/index.php/Special:URIResolver/mw-3AFoo")
    location = resp.headers.get("Location", "")
    if resp.status in (301, 302, 303, 307, 308):
        parsed = urllib.parse.urlparse(location)
        wiki_host = urllib.parse.urlparse(wiki.base).netloc
        assert parsed.netloc in ("", wiki_host), (
            f"Special:URIResolver redirected off-host: {location!r} "
            f"(expected empty or {wiki_host!r}) — CVE-2026-77609 regression"
        )
    else:
        # The patched behaviour for an unresolvable/off-host target is a
        # bad-title error page, not a redirect at all.
        assert resp.status == 200


# ─── CVE-2026-77610 — query debug-output (DebugFormatter) XSS ─────────

def test_ask_debug_output_escapes_the_query_condition(wiki):
    """`Text` is a predefined SMW property shipped on every install — no
    seeded content needed, per the advisory's own PoC shape.
    """
    resp = _ask(wiki, q=f"[[Text::{XSS_PAYLOAD}]]", debug="1")
    _assert_payload_not_reflected_raw(resp, "debug query echo (CVE-2026-77610)")


# ─── GHSA-9rcc-pmj8-ffhr — Special:FacetedSearch cstate XSS ───────────

def test_facetedsearch_cstate_is_escaped(wiki):
    q = "Text"
    csum = zlib.crc32(q.encode("utf-8")) & 0xFFFFFFFF
    resp = wiki.fetch(
        "/index.php?" + urllib.parse.urlencode({
            "title": "Special:FacetedSearch",
            "q": q,
            "csum": str(csum),
            "cstate[0]": XSS_PAYLOAD,
        })
    )
    assert resp.status == 200, f"Special:FacetedSearch answered HTTP {resp.status}"
    assert "onerror=alert" not in resp.text, (
        f"the cstate hidden-input value reflects the payload unescaped.\n"
        f"URL: {resp.url}"
    )


# ─── CVE-2025-61682 — stored XSS via the subtab data attribute ────────

@pytest.fixture(scope="module")
def subtab_xss_page(wiki):
    """Create (or reuse) a throwaway page carrying the advisory's PoC
    wikitext, then return its rendered Special:Ask... no — its own view.

    {{#tag:div|...}} is a core parser function; no SMW markup needed beyond
    the class/data attribute pair the advisory PoC uses.
    """
    title = "DEPS02_CVE_2025_61682_probe"
    text = (
        '{{#tag:div|'
        '|class=smw-subtab'
        '|data-subtab=""<img src=\'\' onerror=alert(1)>""'
        '}}'
    )
    token = wiki.api(action="query", meta="tokens", type="csrf")["query"]["tokens"]["csrftoken"]
    edit = wiki.api_post({
        "action": "edit",
        "title": title,
        "text": text,
        "token": token,
        "bot": 1,
        "summary": "DEPS-02 CVE-2025-61682 regression probe",
    })
    assert "edit" in edit and edit["edit"].get("result") == "Success", (
        f"could not create the probe page: {edit}"
    )
    return title


def test_subtab_data_attribute_cannot_be_forged_via_wikitext(wiki, subtab_xss_page):
    resp = wiki.fetch("/index.php/" + subtab_xss_page)
    assert resp.status == 200
    assert "data-subtab=" not in resp.text, (
        "a user-authored data-subtab attribute survived into the rendered "
        "page — MediaWiki's Sanitizer should strip it under this patch's "
        "data-mw-subtab rename, but it (or an unrenamed one) got through. "
        "CVE-2025-61682 regression."
    )
    # Sanity: the tag itself rendered (class survives; only the reserved
    # data-mw-* prefix is stripped from user wikitext), otherwise the two
    # assertions above are vacuously true because the div never rendered.
    assert "smw-subtab" in resp.text, (
        "the probe page's div did not render at all — cannot conclude "
        "anything about the data attribute; check the wikitext still parses"
    )
