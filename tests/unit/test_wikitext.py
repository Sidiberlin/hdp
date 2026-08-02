"""Tests for docker/haystack/wikitext.py.

These functions decide what text ends up in OpenSearch and what metadata the
RAG pipeline's ranker and prompt see. When one of them degrades it does not
raise — ingestion completes, exits 0, and reports a document count. The wiki
just answers questions worse. That failure mode is the reason these are worth
pinning.

Standard library only.
"""
import pytest
from wikitext import (
    NAMESPACE_TEXT,
    build_metadata,
    build_title_levels,
    decode_varbinary,
    make_prefixed_title,
    split_by_sections,
    strip_tags,
)

# ─── strip_tags ─────────────────────────────────────────────────────────

def test_strip_tags_removes_markup_and_keeps_text():
    assert strip_tags("<p>Hello <b>world</b></p>") == "Hello world"


def test_strip_tags_resolves_character_references():
    """HTMLParser runs with convert_charrefs=True, so entities arrive decoded.

    This matters downstream: an un-decoded "&amp;" would be embedded and
    indexed literally, and a user searching for the real text would miss it.
    """
    assert strip_tags("a &amp; b &lt;c&gt;") == "a & b <c>"
    assert strip_tags("x&nbsp;y") == "x\xa0y"


def test_strip_tags_keeps_script_body_like_php_strip_tags():
    """Matches PHP's strip_tags(), which the docstring claims equivalence to:
    the tags go, the text between them stays."""
    assert strip_tags("<p>x</p><script>bad()</script>") == "xbad()"


def test_strip_tags_on_empty_and_tag_only_input():
    assert strip_tags("") == ""
    assert strip_tags("<br/><hr/>") == ""


def test_strip_tags_tolerates_unclosed_tags():
    """MediaWiki output is well-formed, but a truncated API response is not,
    and this must not raise in the middle of a reindex."""
    assert strip_tags("<p>text") == "text"


# ─── build_title_levels ─────────────────────────────────────────────────

@pytest.mark.parametrize(
    "title,expected",
    [
        ("A", ["A", "", "", "", ""]),
        ("A/B/C", ["A", "B", "C", "", ""]),
        ("Help:Foo_Bar/Baz", ["Help:Foo Bar", "Baz", "", "", ""]),
        ("", ["", "", "", "", ""]),
        # Deeper than five levels is truncated, not an error. The prompt only
        # has five slots.
        ("A/B/C/D/E/F/G", ["A", "B", "C", "D", "E"]),
    ],
)
def test_build_title_levels(title, expected):
    levels = build_title_levels(title)
    assert [levels[f"title_level_{i}"] for i in range(1, 6)] == expected


def test_build_title_levels_always_returns_five_keys():
    """The prompt template reads title_level_1..5 unconditionally; a missing
    key would be a KeyError at query time, not at ingestion time."""
    assert set(build_title_levels("A").keys()) == {f"title_level_{i}" for i in range(1, 6)}


# ─── make_prefixed_title ────────────────────────────────────────────────

@pytest.mark.parametrize(
    "namespace,title,expected",
    [
        (0, "Foo_Bar", "Foo Bar"),          # main namespace gets no prefix
        (12, "Foo", "Help:Foo"),
        (102, "A_B", "Property:A B"),
        (5000, "X", "Ministerium:X"),
        (5002, "Y", "Projektträger:Y"),
        (999, "Y", "Y"),                    # unknown namespace: no prefix, no crash
        (0, "", ""),
    ],
)
def test_make_prefixed_title(namespace, title, expected):
    assert make_prefixed_title(namespace, title) == expected


def test_every_indexable_namespace_has_a_name():
    """INDEXABLE_NAMESPACES in ingest_hdp_wiki.py is [0, 12, 5000, 5002]. A
    namespace that is indexed but unnamed here silently loses its prefix, so
    the citation link the chatbot renders would point at the wrong page."""
    for ns in (0, 12, 5000, 5002):
        assert ns in NAMESPACE_TEXT


# ─── decode_varbinary ───────────────────────────────────────────────────

def test_decode_varbinary_decodes_utf8_bytes():
    assert decode_varbinary("Café".encode()) == "Café"


def test_decode_varbinary_passes_through_non_bytes():
    assert decode_varbinary("plain") == "plain"
    assert decode_varbinary(None) is None
    assert decode_varbinary(42) == 42


def test_decode_varbinary_replaces_undecodable_bytes_instead_of_raising():
    """errors="replace" is load-bearing. page_title is a varbinary column and
    one malformed row must not abort a full reindex with a UnicodeDecodeError."""
    out = decode_varbinary(b"\xff\xfe")
    assert isinstance(out, str)
    assert "�" in out


# ─── split_by_sections ──────────────────────────────────────────────────

def test_split_by_sections_splits_intro_and_headings():
    html = "Intro text.<h2>First</h2>Body one.<h3>Second</h3>Body two."
    assert split_by_sections(html) == [
        {"section_name": "Intro", "content": "Intro text."},
        # The heading text is part of the section body too. That is existing
        # behaviour and it is arguably useful — the heading is a strong
        # retrieval signal — but it is not obvious, so it is pinned here.
        {"section_name": "First", "content": "FirstBody one."},
        {"section_name": "Second", "content": "SecondBody two."},
    ]


@pytest.mark.parametrize("level", [1, 2, 3, 4, 5, 6])
def test_split_by_sections_recognises_every_heading_level(level):
    sections = split_by_sections(f"<h{level}>Head</h{level}>Body.")
    assert [s["section_name"] for s in sections] == ["Head"]


def test_split_by_sections_is_case_insensitive():
    assert split_by_sections("<H2>Head</H2>Body.")[0]["section_name"] == "Head"


def test_split_by_sections_falls_back_to_full_page_without_headings():
    assert split_by_sections("<p>Only body.</p>") == [
        {"section_name": "Full Page", "content": "Only body."}
    ]


def test_split_by_sections_returns_nothing_for_empty_content():
    """An empty list means process_page writes zero documents for the page,
    which is the right answer for a page with no text."""
    assert split_by_sections("") == []
    assert split_by_sections("   <p>  </p> ") == []


def test_split_by_sections_does_not_split_the_non_legacy_heading_dom():
    """Documents a live upgrade risk. Do not "fix" this without Wave 3.

    The heading regex is `<h[1-6]>` — a bare tag, no attributes. MediaWiki
    1.43 (MW_VERSION in app/includes/Defines.php is 1.43.5) still emits that
    form because $wgParserEnableLegacyHeadingDOM defaults to true and this
    repo does not override it, so ingestion splits correctly today.

    That flag is transitional. MainConfigSchema.php documents it as `@since
    1.43` for https://www.mediawiki.org/wiki/Heading_HTML_changes, and the new
    structure emits:

        <div class="mw-heading mw-heading2"><h2 id="Sec">Sec</h2></div>

    which this regex cannot match. When upstream flips the default, every page
    silently collapses into one "Full Page" document instead of one document
    per section. Nothing raises, ingestion still exits 0, and it still reports
    a document count — retrieval just gets worse. This test exists so that the
    day the behaviour changes, something goes red.
    """
    non_legacy = (
        '<p>Intro.</p>'
        '<div class="mw-heading mw-heading2"><h2 id="Sec">Sec</h2></div>'
        '<p>Body.</p>'
    )
    sections = split_by_sections(non_legacy)
    assert [s["section_name"] for s in sections] == ["Full Page"], (
        "split_by_sections now handles the non-legacy heading DOM. If that was "
        "intentional, update this test and docs/dev/AGENTS.md; if it was not, "
        "ingestion just stopped splitting pages into sections."
    )


# ─── build_metadata ─────────────────────────────────────────────────────

PAGE = {"page_id": 7, "page_namespace": 12, "page_title": "Cloud_Computing"}


def test_build_metadata_core_fields():
    meta = build_metadata({}, PAGE, "Intro")
    assert meta["prefixed_title"] == "Help:Cloud Computing"
    assert meta["namespace"] == 12
    assert meta["namespace_text"] == "Help"
    assert meta["page_id"] == 7
    assert meta["sourcekey"] == "wikipage"
    assert meta["title_level_1"] == "Help:Cloud Computing"
    assert meta["sections"] == ["Intro"]


def test_build_metadata_uri_uses_underscores():
    """The wiki serves Help:Cloud_Computing, not Help:Cloud%20Computing. This
    URI is what the chatbot renders as the citation link, so a wrong form here
    is a broken link in every answer that cites the page."""
    assert build_metadata({}, PAGE, "Intro")["uri"].endswith("/w/Help:Cloud_Computing")


def test_build_metadata_full_page_section_is_not_listed():
    """"Full Page" is a synthetic name from split_by_sections, not a real
    heading, so it must not be presented to the ranker as one."""
    assert build_metadata({}, PAGE, "Full Page")["sections"] == []


def test_build_metadata_reads_categories_from_either_key():
    parsed = {"categories": [{"*": "Alpha"}, {"category": "Beta"}, {}]}
    assert build_metadata(parsed, PAGE, "Intro")["categories"] == ["Alpha", "Beta", ""]


def test_build_metadata_joins_chatbotmeta_values():
    parsed = {"properties": [
        {"name": "other", "values": ["ignored"]},
        {"name": "ChatbotMeta", "values": ["one", "two"]},
    ]}
    # The name match is case-insensitive; SMW property casing is not guaranteed.
    assert build_metadata(parsed, PAGE, "Intro")["chatbotmeta"] == "one; two"


def test_build_metadata_chatbotmeta_defaults_to_empty():
    assert build_metadata({}, PAGE, "Intro")["chatbotmeta"] == ""


def test_build_metadata_display_title_is_stripped_of_markup():
    parsed = {"displaytitle": '<span class="mw-page-title-main">Cloud</span>'}
    assert build_metadata(parsed, PAGE, "Intro")["display_title"] == "Cloud"


def test_build_metadata_display_title_falls_back_to_prefixed_title():
    for parsed in ({}, {"displaytitle": ""}, {"displaytitle": "<span></span>"}):
        assert build_metadata(parsed, PAGE, "Intro")["display_title"] == "Help:Cloud Computing"


def test_build_metadata_emits_every_field_the_pipeline_reads():
    """hdp_pipeline.yaml's prompt and ranker read these by name. A missing key
    surfaces at query time as a template error, long after ingestion passed."""
    meta = build_metadata({}, PAGE, "Intro")
    required = {
        "title_level_1", "title_level_2", "title_level_3", "title_level_4",
        "title_level_5", "chatbotmeta", "display_title", "sections",
        "prefixed_title", "namespace", "namespace_text", "categories", "tags",
        "sourcekey", "page_id", "uri",
    }
    assert required <= set(meta)
