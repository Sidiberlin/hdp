"""Regression tests for the DEPS-02 SMW backports (docker/patches/smw-*).

Two layers, deliberately independent of `scripts/verify-patches.sh`'s own
`marker`/`anti` regexes (which read the manifest, not the source) — a bug in
one must not be invisible because the other agrees with it:

1. A real `php -r` execution proving the escaping primitive each patch adds
   (`htmlspecialchars(..., ENT_QUOTES [| ENT_SUBSTITUTE], 'UTF-8')`) actually
   neutralises the crafted payload used against this advisory. This is the
   negative control: each payload is asserted UNCHANGED by the identity
   transform (what the pre-patch code did) and CHANGED by the escaping call
   (what the patch does) — so the test only passes because the escaping does
   real work, not because the payload happened to contain nothing special.
2. A source-pattern check against the actual committed file: the vulnerable
   sink is gone, the fixed one is present. Independently authored from the
   manifest's `anti`/`marker` fields (see docker/patches/smw-*.yaml), so a
   sidecar and this test would have to be wrong in the same way to both miss
   a regression.

No live wiki, no docker — this is the unit tier. The T3-level assertions
against a running Special:Ask / Special:SearchByProperty / etc. are
tests/integration/test_smw_advisory_closure.py, which needs the 4-container
profile (see that file's docstring) and is not run from here.
"""
import os
import shutil
import subprocess

import pytest

PHP = shutil.which("php")
requires_php = pytest.mark.skipif(PHP is None, reason="php binary not available on this runner")

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SMW = os.path.join(REPO, "app", "extensions", "SemanticMediaWiki")

XSS_PAYLOAD = "\"><img src=x onerror=alert(document.domain)>"


def _php_escape(payload, flags="ENT_QUOTES | ENT_SUBSTITUTE"):
    """Run the real htmlspecialchars() call each patch adds, via php -r.

    Executing the actual PHP builtin (not a Python re-implementation) is the
    point: it is the same function call the patched source makes, with the
    same flags, so a change to PHP's own escaping behaviour would show up
    here too.
    """
    script = (
        f"$p = {payload!r}; "
        f"echo htmlspecialchars($p, {flags}, 'UTF-8');"
    )
    out = subprocess.run(["php", "-r", script], capture_output=True, text=True, check=True)
    return out.stdout


def _read(rel_path):
    with open(os.path.join(SMW, rel_path), encoding="utf-8") as f:
        return f.read()


@requires_php
def test_php_available():
    subprocess.run(["php", "--version"], capture_output=True, check=True)


# ─── the escaping primitive, actually executed ───────────────────────

@requires_php
def test_htmlspecialchars_neutralises_the_xss_payload():
    """The negative control shared by every ENT_QUOTES|ENT_SUBSTITUTE patch.

    Fails (proving the payload is a real test) if htmlspecialchars ever
    stopped encoding '<', '>' or '"' under these flags; passes because the
    patched sinks all route through exactly this call.
    """
    raw = XSS_PAYLOAD
    escaped = _php_escape(raw)

    # Negative control: the identity transform (what every one of these
    # sinks did pre-patch) leaves the payload exploitable.
    assert "<img" in raw and "onerror=" in raw

    # The fix: escaped output cannot re-enter HTML tag/attribute context.
    assert "<img" not in escaped
    assert "&lt;img" in escaped
    assert "&quot;" in escaped or "&#034;" in escaped


@requires_php
def test_the_br_allowlist_still_passes_through_unescaped():
    """smw-ask-sep-xss's allowlist: legitimate <br> separators must survive."""
    for variant in ("<br>", "<br/>", "<br />", "  <BR>  "):
        script = (
            f"$sep = {variant!r};"
            "if (preg_match('#^\\s*<br\\s*/?>\\s*$#i', $sep)) { echo $sep; }"
            "else { echo htmlspecialchars($sep, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }"
        )
        out = subprocess.run(["php", "-r", script], capture_output=True, text=True, check=True)
        assert out.stdout == variant, f"{variant!r} should pass through unescaped"

    # Negative control: something that merely contains "br" is still escaped.
    script = (
        "$sep = '<br onmouseover=alert(1)>';"
        "if (preg_match('#^\\s*<br\\s*/?>\\s*$#i', $sep)) { echo 'ALLOWED'; }"
        "else { echo 'ESCAPED'; }"
    )
    out = subprocess.run(["php", "-r", script], capture_output=True, text=True, check=True)
    assert out.stdout == "ESCAPED"


# ─── source-pattern checks, independent of the manifest ──────────────

def test_ask_sep_xss_source():
    src = _read("src/Query/ResultPrinters/TableResultPrinter.php")
    assert "implode( $this->params['sep'], $values )" not in src, \
        "the raw, unescaped sep join is back — CVE-2026-77607 regression"
    assert "getValueSeparator( $outputMode )" in src
    assert "ENT_QUOTES | ENT_SUBSTITUTE" in src


def test_ask_plain_header_xss_source():
    src = _read("src/Query/ResultPrinters/TableResultPrinter.php")
    assert "if ( $this->isHTML && $isPlain )" in src
    assert "htmlspecialchars( (string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' )" in src


def test_searchbyproperty_error_xss_source():
    src = _read("src/MediaWiki/Specials/SearchByProperty/PageBuilder.php")
    # The two unescaped-return sites CVE-2026-77608 exploited.
    assert "return [ ProcessingErrorMsgHandler::getMessagesAsString(" not in src, \
        "an unescaped error return is back — CVE-2026-77608 regression"
    assert src.count("escapeErrorMessage(") >= 3  # the helper def + 2 call sites


def test_uriresolver_open_redirect_source():
    src = _read("src/MediaWiki/Specials/SpecialURIResolver.php")
    assert "$out->redirect( $title->getFullURL(), '303' )" not in src, \
        "the unconditional, unvalidated redirect is back — CVE-2026-77609 regression"
    assert "isLocalRedirectTarget(" in src
    assert "strcasecmp( $targetHost, $serverHost )" in src


def test_debug_query_xss_source():
    src = _read("src/Query/DebugFormatter.php")
    assert "str_replace( '[', '&#91;', $description->getQueryString() ?? '' )" not in src, \
        "the unescaped ASK-query echo is back — CVE-2026-77610 regression"
    assert "$errors .= $error . '<br />'" not in src, \
        "the unescaped error-list echo is back — CVE-2026-77610 regression"
    assert "htmlspecialchars( $description->getQueryString() ?? '', ENT_QUOTES )" in src
    assert "htmlspecialchars( (string)$error, ENT_QUOTES )" in src
    assert "$sql = htmlspecialchars( (string)$sql, ENT_QUOTES );" in src


def test_facetedsearch_cstate_xss_source():
    src = _read("src/MediaWiki/Specials/FacetedSearch/HtmlBuilder.php")
    assert '\'<input name="\' . "cstate[$key]" . \'" type="hidden" value="\' . $value . \'">\'' not in src, \
        "the unescaped cstate concatenation is back — GHSA-9rcc-pmj8-ffhr regression"
    assert "Html::hidden( \"cstate[$key]\", $value )" in src
    assert "is_scalar( $value )" in src
    assert "use MediaWiki\\Html\\Html;" in src


def test_subtab_xss_source_php_and_js_agree():
    php_src = _read("src/Utils/HtmlTabs.php")
    js_src = _read("res/smw/ext.smw.js")

    assert "$attributes['data-subtab'] = json_encode( $tabs )" not in php_src, \
        "the forgeable data-subtab attribute is back — CVE-2025-61682 regression"
    assert "$attributes['data-mw-subtab'] = json_encode( $tabs )" in php_src

    assert "x[i].dataset.subtab" not in js_src, \
        "ext.smw.js reads the forgeable attribute again — CVE-2025-61682 regression"
    assert "x[i].dataset.mwSubtab" in js_src

    # The two halves must name the same attribute, or the pair is a no-op:
    # PHP writing data-mw-subtab while JS still reads data-subtab (or vice
    # versa) would silently break subtabs without reopening the XSS, and a
    # test that checked each file in isolation would not catch the mismatch.
    assert "data-mw-subtab" in php_src and "mwSubtab" in js_src


def test_reserved_data_mw_prefix_is_what_makes_the_subtab_fix_work():
    """The premise the whole smw-subtab-xss pair rests on: MediaWiki's
    Sanitizer actually strips data-mw-* from user-supplied wikitext. If this
    ever stopped being true, renaming the attribute would not fix anything.
    """
    sanitizer = os.path.join(REPO, "app", "includes", "parser", "Sanitizer.php")
    with open(sanitizer, encoding="utf-8") as f:
        src = f.read()
    assert "isReservedDataAttribute" in src
    assert "data-mw" in src
