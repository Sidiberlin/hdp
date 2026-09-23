"""DEPS-02: the live-request shape of the six reflected/stored XSS and the
one open-redirect backported against vendored SemanticMediaWiki 6.0.1 (see
docker/ci/composer-audit-baseline.json's mediawiki/semantic-media-wiki entry
and docs/dev/patches.md "The SMW set").

**The `sep` test (CVE-2026-77607) is O1 live-repro evidence, not a guess.**
The orchestrator captured it against the QA box (SMW 6.0.1, authenticated
session): `sep` only reaches `TableResultPrinter` through the compact
Infolink `p=` parameter (`SMWInfolink::decodeParameters()`,
`includes/SMW_Infolink.php:550`) — a bare `sep=` or a `params[sep]=` array
never reaches the printer, confirmed on-box. Pre-patch, the payload landed
raw inside the `<td>` joining a multi-value property's values, sink
`TableResultPrinter.php:355`'s `implode`. That requires a page with *two*
values of one property (a single value never invokes the separator at all)
and an authenticated request — `request_type=raw` is read-gated by
BlueSpice on this deployment, so the anonymous raw-output half of
CVE-2026-77607 is not independently exercised here; the authenticated table
path is. See `_compact_p()` and `test_ask_sep_parameter_is_escaped` below.

Every other URL and parameter name in this file is traced from the
vulnerable/patched PHP itself rather than captured live (`SpecialAsk`'s
`debug` request parameter, `SpecialSearchByProperty`, `SpecialURIResolver`,
`SpecialFacetedSearch`'s `checkRequest()` checksum gate) — this session has
no live-infrastructure access beyond the one O1 capture relayed above.
**Everything except the sep test has not been run.** Treat a first run of
those as a shakedown, not a rubber stamp — request shapes other than `sep`'s
may need adjusting once O2's fuller live pass happens. Given `sep`'s own
correction (a plausible-looking `p[sep]=` array form silently not working),
the other Special:Ask test here (`mainlabel`/`headers=plain`) is written
using the same confirmed `p=` compact mechanism rather than the array form,
on the working assumption that the same deployment quirk applies to it too.

Unmarked (no `pytestmark`), so it runs in T3 like the rest of this
directory: it needs the wiki and `mediawiki-web`, nothing else — except
test_smws_own_subtab_markup_uses_the_reserved_attribute, which reaches the
mediawiki container through mw_eval because its target class has no caller
to route an HTTP request through (the SMW special pages under test are
otherwise HTML page views, not job-queue or search-index dependent — see
test_search.py's docstring for the contrast).

Each advisory gets one positive assertion (patched behaviour) plus the
negative control the plan's AC4 asks for: reverting the corresponding
`docker/patches/smw-*.patch` (e.g. `patch -R -p0 -d app <
docker/patches/smw-ask-sep-xss.patch` from the repo root, then
`docker compose exec mediawiki php maintenance/run.php
DumpRenderedHtml.php` is unnecessary — the bind mount is live, see
docs/dev/CLAUDE.md "Bind mounts are live") before re-running this file must
turn every assertion below red, with one deliberate exception:
test_the_reserved_subtab_attribute_cannot_be_forged_via_wikitext pins MediaWiki
core's reserved-prefix behaviour rather than SMW's patch, so it stays green
through the round trip by design; the subtab pair's two controls are the
served-module and HtmlTabs tests. Re-applying the reverted patch must turn
every other assertion green again. That round trip is what proves these are
real regression tests and not tautologies — see tests/unit/test_smw_xss_mitigations.py for the same
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

XSS_PAYLOAD = '"><script>alert(1)</script>'
XSS_PAYLOAD_RAW_MARKER = "<script>alert(1)</script>"


def _compact_p(paramstring):
    """SMW's compact Infolink `p=` encoding for a single `key=value` #ask
    parameter, as a GET query-string value.

    `SMWInfolink::decodeParameters()` (includes/SMW_Infolink.php:550)
    reverses this with `rawurldecode( str_replace( '-', '%', $p ) )`, so the
    forward direction is `rawurlencode($paramstring)` with every resulting
    `%` turned into `-`. This is the mechanism O1's live repro confirmed is
    required for `sep` to reach the printer at all — see the module
    docstring. Must not be used with a paramstring containing a literal `-`
    (it would collide with the substitution); none of this file's payloads
    do.
    """
    assert "-" not in paramstring, "payload contains '-', which _compact_p cannot encode safely"
    return urllib.parse.quote(paramstring, safe="").replace("%", "-")


def _ask(wiki, compact=None, **params):
    """GET Special:Ask. `title`/`q`/`po`/`debug`/`format`/`limit` are bare
    query-string keys (confirmed live for format/limit via O1's captured
    URL; the others read directly off $request in SpecialAsk/ParametersProcessor).
    `compact` is a list of raw `key=value` #ask-parameter strings (sep,
    mainlabel, headers, ...) sent through the single `p=` compact Infolink
    parameter — see `_compact_p()`.
    """
    qp = {"title": "Special:Ask"}
    for key in ("q", "po", "debug", "format", "limit"):
        if key in params:
            qp[key] = params.pop(key)
    assert not params, f"unexpected bare params, did you mean compact=[...]? {params}"
    if compact:
        qp["p"] = "/".join(_compact_p(entry) for entry in compact)
    return wiki.fetch("/index.php?" + urllib.parse.urlencode(qp))


def _assert_payload_not_reflected_raw(resp, label):
    assert resp.status == 200, f"{label}: Special:Ask answered HTTP {resp.status}"
    assert XSS_PAYLOAD_RAW_MARKER not in resp.text, (
        f"{label}: the crafted <script> tag appears verbatim in the "
        f"response body — the payload was not escaped.\n"
        f"URL: {resp.url}"
    )
    # The escaped form must be present, not merely "the raw form is absent"
    # (which a truncated or errored response would also satisfy).
    assert "&lt;script&gt;" in resp.text or "&#60;script&#62;" in resp.text, (
        f"{label}: expected an HTML-escaped <script> tag somewhere in the "
        f"response.\nURL: {resp.url}"
    )


PROP = "Has number"  # predefined-adjacent, numeric-typed property named in O1's repro


@pytest.fixture(scope="module")
def two_valued_property_page(wiki):
    """A page with two values of one property — CVE-2026-77607's sink is
    the `implode()` joining a *multi*-value cell; a single value never
    invokes the separator at all (per O1's live repro). The values
    themselves are plain numbers; only `sep`, supplied at query time, ever
    carries the payload.
    """
    title = "DEPS02_CVE_2026_77607_probe"
    text = f"[[{PROP}::1]] [[{PROP}::2]]"
    token = wiki.api(action="query", meta="tokens", type="csrf")["query"]["tokens"]["csrftoken"]
    edit = wiki.api_post({
        "action": "edit",
        "title": title,
        "text": text,
        "token": token,
        "bot": 1,
        "summary": "DEPS-02 CVE-2026-77607 regression probe",
    })
    assert "edit" in edit and edit["edit"].get("result") == "Success", (
        f"could not create the probe page: {edit}"
    )
    return title


# ─── CVE-2026-77607 — Special:Ask table `sep` XSS ─────────────────────
# O1 live repro against the QA box (SMW 6.0.1, authenticated session).

def test_ask_sep_parameter_is_escaped(wiki, two_valued_property_page):
    resp = _ask(
        wiki,
        q=f"[[{PROP}::+]]",
        po=f"?{PROP}",
        format="table",
        limit="10",
        compact=[f"sep={XSS_PAYLOAD}"],
    )
    _assert_payload_not_reflected_raw(resp, "sep (CVE-2026-77607)")


def test_ask_sep_br_allowlist_still_renders_a_real_linebreak(wiki, two_valued_property_page):
    """The fix's one deliberate exception: <br> must still work as a
    separator, or the patch traded an XSS for a broken feature.
    """
    resp = _ask(
        wiki,
        q=f"[[{PROP}::+]]",
        po=f"?{PROP}",
        format="table",
        limit="10",
        compact=["sep=<br>"],
    )
    assert resp.status == 200
    assert "<br>" in resp.text or "<br/>" in resp.text or "<br />" in resp.text


# ─── CVE-2026-77606 — Special:Ask plain-header (mainlabel) XSS ────────
# Not O1-captured; written on the working assumption that mainlabel needs
# the same p= compact mechanism sep turned out to need (see module
# docstring) — unverified, flag for O2.

def test_ask_plain_header_mainlabel_is_escaped(wiki, two_valued_property_page):
    resp = _ask(
        wiki,
        q=f"[[{PROP}::+]]",
        po=f"?{PROP}",
        format="table",
        limit="10",
        compact=["headers=plain", f"mainlabel={XSS_PAYLOAD}"],
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
    assert XSS_PAYLOAD_RAW_MARKER not in resp.text, (
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
    assert XSS_PAYLOAD_RAW_MARKER not in resp.text, (
        f"the cstate hidden-input value reflects the payload unescaped.\n"
        f"URL: {resp.url}"
    )


# ─── CVE-2025-61682 — stored XSS via the subtab data attribute ────────
#
# Read the three tests below together, because what this advisory's fix does
# is not what it looks like. GHSA-hg8h-557g-q8pp is explicit about the
# mechanism: "most data attributes (except for reserved ones) like
# data-subtab can be used in wikitext", so ext.smw.js was JSON.parse()ing +
# innerHTML'ing a value any editor could author. Upstream SMW 7.0.0's fix —
# which docker/patches/smw-subtab-xss-{php,js}.patch backport verbatim — is
# the *rename*, not a sanitiser rule: `data-mw-*` is reserved
# (Sanitizer::isReservedDataAttribute(), app/includes/parser/Sanitizer.php:604,
# enforced at Sanitizer.php:523) and stripped from user wikitext, while plain
# `data-*` is explicitly allowed. So after the fix a forged `data-subtab`
# still renders; it is simply never read again.
#
# An earlier version of this section asserted `"data-subtab=" not in page`,
# an invariant MediaWiki has never provided. It failed on every T3 run the
# job has ever done (35862219039, 35864784956, 35870127456) with the same
# escaped div every time:
#
#   <div class="smw-subtab" data-subtab="&quot;&lt;img src=&#39;&#39; onerror=alert(1)&gt;&quot;">
#
# present, escaped, inert. The three properties below are the ones the patch
# pair actually buys, and two of them are reverting-red negative controls
# (DEPS-02 AC4): one per half of the pair.
#
# Not asserted on purpose: "the payload never reaches ext.smw.js" as a DOM
# fact. On a page with a wikitext-forged class="smw-subtab" element,
# JSON.parse(dataset.mwSubtab) gets undefined and throws, aborting the module
# before it touches innerHTML — pre-existing upstream fragility, identical
# pre-patch, and it would confound any browser-level assertion.

SUBTAB_PAYLOAD = '""<img src=\'\' onerror=alert(1)>""'
SUBTAB_PAYLOAD_RAW_MARKER = "<img src=''"


@pytest.fixture(scope="module")
def subtab_xss_page(wiki):
    """Create (or refresh) one page carrying both spellings of the advisory's
    PoC attribute, and return its title.

    `{{#tag:div|...}}` is a core parser function, so no SMW markup is needed
    beyond the class/data-attribute pair the PoC uses. Both spellings sit on
    one page so a single fetch shows the asymmetry the whole fix rests on:
    the plain `data-*` one survives, the reserved `data-mw-*` one does not.
    The third div checks the reserved-prefix match is case-insensitive, which
    `isReservedDataAttribute()`'s `/i` flag promises and a future refactor
    could silently drop.
    """
    title = "DEPS02_CVE_2025_61682_probe"
    text = (
        "{{#tag:div||class=smw-subtab|data-subtab=" + SUBTAB_PAYLOAD + "}}\n"
        "{{#tag:div||class=smw-subtab|data-mw-subtab=" + SUBTAB_PAYLOAD + "}}\n"
        "{{#tag:div||class=smw-subtab|DATA-MW-SUBTAB=" + SUBTAB_PAYLOAD + "}}\n"
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


def test_the_reserved_subtab_attribute_cannot_be_forged_via_wikitext(wiki, subtab_xss_page):
    """The premise the smw-subtab-xss pair rests on, checked against a live
    render rather than by reading Sanitizer.php — tests/unit/
    test_smw_xss_mitigations.py::test_reserved_data_mw_prefix_is_what_makes_the_subtab_fix_work
    does the static half.

    This one does *not* go red when the patches are reverted, and that is
    correct: it pins MediaWiki core's behaviour, not SMW's. If core ever
    stopped reserving `data-mw-*`, the rename would protect nothing and the
    pair would have to be replaced with real sanitisation — that is the
    regression this test exists to catch, and nothing else in the suite would.
    """
    resp = wiki.fetch("/index.php/" + subtab_xss_page)
    assert resp.status == 200, f"the probe page answered HTTP {resp.status}"

    # Non-vacuity first: all three divs rendered, so the absences below are
    # facts about attributes and not about a page that never parsed.
    assert resp.text.count("smw-subtab") >= 3, (
        f"expected three smw-subtab divs, found "
        f"{resp.text.count('smw-subtab')} — the probe page did not render, so "
        f"nothing below can be concluded; check {{{{#tag:div}}}} still parses.\n"
        f"URL: {resp.url}"
    )

    assert "data-mw-subtab" not in resp.text.lower(), (
        "a user-authored data-mw-subtab attribute survived into the rendered "
        "page. MediaWiki's Sanitizer is supposed to strip the reserved "
        "data-mw-* prefix from wikitext (isReservedDataAttribute(), "
        "app/includes/parser/Sanitizer.php), and the smw-subtab-xss pair "
        "depends on precisely that — it moves SMW's attribute into the "
        "reserved namespace instead of sanitising the payload. If the prefix "
        "is forgeable, CVE-2025-61682 is open again by another route.\n"
        f"URL: {resp.url}"
    )

    # The plain data-* spelling is *expected* to survive: MediaWiki allows any
    # non-reserved data-* attribute in wikitext and the fix is that nothing
    # reads it any more (next test). What must never survive is a break-out of
    # the attribute value into markup.
    assert SUBTAB_PAYLOAD_RAW_MARKER not in resp.text, (
        "the PoC <img> tag appears verbatim in the rendered page — the forged "
        "attribute value escaped its quoting, which is live XSS regardless of "
        "which attribute name SMW reads.\n"
        f"URL: {resp.url}"
    )
    assert "&lt;img" in resp.text, (
        "neither the raw nor the escaped <img> is in the page: the payload "
        "vanished entirely, which makes the assertion above vacuously true.\n"
        f"URL: {resp.url}"
    )


def test_the_served_smw_module_reads_only_the_reserved_subtab_attribute(wiki):
    """The JS half of the pair, read off the wiki's own ResourceLoader output
    instead of off the file in the repo.

    This is the deployed-state negative control. `ext.smw` is a Class A patch
    target: `composer install` reinstalls SemanticMediaWiki as a dist zipball
    and wipes both halves on every run, and scripts/apply-patches.sh has to
    put them back (docs/dev/patches.md, "The SMW set").
    tests/unit/test_smw_xss_mitigations.py asserts the bytes on disk; only a
    load.php fetch asserts what a browser is actually handed, which also
    covers a stale ResourceLoader cache. Revert
    docker/patches/smw-subtab-xss-js.patch and this goes red — the forgeable
    read `x[i].dataset.subtab` comes back, in the minified output too.
    """
    resp = wiki.fetch("/load.php?modules=ext.smw&only=scripts&raw=1")
    assert resp.status == 200, f"load.php answered HTTP {resp.status}"

    # Confirm the right module was served before asserting on an absence: an
    # unknown module name is a 200 with no code, which would satisfy the
    # negative assertion for the wrong reason.
    assert "smw-subtab" in resp.text, (
        "the ext.smw module body never mentions smw-subtab — load.php served "
        "something else (unknown module names answer 200 with an empty body).\n"
        f"URL: {resp.url}"
    )
    assert "dataset.mwSubtab" in resp.text, (
        "the served ext.smw module does not read dataset.mwSubtab: "
        "smw-subtab-xss-js is missing from the running wiki. Either composer "
        "reinstalled SemanticMediaWiki without apply-patches.sh re-applying "
        "it, or ResourceLoader is serving a cached pre-patch module. "
        "CVE-2025-61682 regression.\n"
        f"URL: {resp.url}"
    )
    assert "dataset.subtab" not in resp.text, (
        "the served ext.smw module still reads dataset.subtab — the "
        "wikitext-forgeable attribute. Any editor can set it via "
        "{{#tag:div|class=smw-subtab|data-subtab=...}} and have this line "
        "JSON.parse() + innerHTML the result. CVE-2025-61682 regression.\n"
        f"URL: {resp.url}"
    )


def test_smws_own_subtab_markup_uses_the_reserved_attribute(mw_eval):
    """The PHP half of the pair, exercised rather than grepped.

    It has to be driven directly: `grep -rn 'isSubTab(' app/` finds only the
    class itself, so nothing in this tree renders a real SMW subtab and there
    is no page view to assert against (every Special: page in this file
    reports zero data-mw-subtab attributes for exactly that reason). Without
    this test the PHP half has no T3-tier negative control, and DEPS-02 AC4
    ("the T3 assertions fail with the patches reverted") would hold for only
    half of the pair.

    Both directions matter. PHP writing data-subtab while JS reads
    data-mw-subtab breaks subtabs silently; PHP writing data-subtab while JS
    reads it too reopens the CVE. Revert
    docker/patches/smw-subtab-xss-php.patch and this goes red.
    """
    out = mw_eval(
        "$t = new SMW\\Utils\\HtmlTabs();"
        "$t->isSubTab();"
        "$t->tab( 'foo', 'FOO' );"
        "$t->content( 'foo', '<...bar...>' );"
        'echo "HTMLTABS:" . $t->buildHTML( [ "class" => "probe" ] );'
    )
    # eval.php prints a banner and blank lines around the result on some
    # builds, so anchor on the marker the snippet echoes itself.
    assert "HTMLTABS:" in out, f"eval.php produced no marked output:\n{out[-2000:]}"
    html = out.split("HTMLTABS:", 1)[1]

    assert "smw-subtab" in html, (
        f"HtmlTabs::buildHTML() emitted no subtab div at all, so the "
        f"assertions below would be vacuous:\n{html!r}"
    )
    assert "data-mw-subtab=" in html, (
        "HtmlTabs::buildHTML() does not attach the renamed data-mw-subtab "
        "attribute — smw-subtab-xss-php is not in the running tree, so the "
        "JS half is reading an attribute nobody writes. "
        f"CVE-2025-61682 regression.\n{html!r}"
    )
    assert "data-subtab=" not in html, (
        "HtmlTabs::buildHTML() still attaches the wikitext-forgeable "
        f"data-subtab attribute. CVE-2025-61682 regression.\n{html!r}"
    )
