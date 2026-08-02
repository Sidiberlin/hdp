"""Golden files for the convert-docs post-processor.

`scripts/convert-docs.sh` turns `docs/wiki/*.md` into the wikitext under
`docker/mediawiki/wiki-docs/`, which `docker/setup.sh` seeds into the Help
namespace on first boot. Its output was untested until Wave 3, and it is
exactly the kind of code that fails quietly: every transform here produces
*valid* wikitext whether or not it is correct, so a regression does not throw,
does not fail setup.sh, and does not fail a page load. It renders a page of
`--&gt;` where a diagram should be, and somebody notices weeks later.

Golden files rather than hand-written expectations, because the outputs are
long and the point of reviewing a change to this module is reading the diff of
what it produced.

The inputs are inline instead of committed files: each one is a small,
labelled sample of what pandoc emits for one construct, and it reads better
next to the assertion than in a file two directories away. The *outputs* are
the committed artefacts.

Regenerate with `scripts/ci/pytest.sh --regen-golden`, then read the diff.
"""
import convert_docs_postprocess as cdp
import pytest

# A stable URL, so a golden file does not change when the repo moves hosts.
REPO_URL = "https://example.invalid/hdp/-/blob/main"

# (golden name, source path the text came from, pandoc output)
#
# `src` matters: relative links resolve against its directory, so the same
# input text from README.md and from modules/ingestion.md must produce
# different links. Two of the cases below exist to pin that down.
CASES = [
    (
        "mermaid_pre_block",
        "docs/wiki/architecture.md",
        '<pre class="mermaid">graph TD\n'
        "  A[Wiki] --&gt;|HTTP| B[Haystack]\n"
        '  B --&gt; C{{"OpenSearch"}}\n'
        '  C --&gt; D[&quot;Antwort&quot;]\n'
        "</pre>\n",
    ),
    (
        "mermaid_syntaxhighlight_block",
        "docs/wiki/diagrams/sequences.md",
        '<syntaxhighlight lang="mermaid">sequenceDiagram\n'
        "  User --&gt;&gt; Wiki: Frage\n"
        "  Wiki --&gt;&gt; Haystack: /chat-stream\n"
        "</syntaxhighlight>\n",
    ),
    (
        "heading_anchor_spans",
        "docs/wiki/README.md",
        '<span id="uberblick"></span>\n'
        "== Überblick ==\n\n"
        'Text.<span id="inline"></span> Mehr Text.\n\n'
        '<span id="details"></span>\n'
        "=== Details ===\n",
    ),
    (
        "links_between_doc_pages",
        "docs/wiki/README.md",
        "Siehe [[architecture.md|Architektur]] und\n"
        "[[modules/ingestion.md|Ingestion]] sowie\n"
        "[[getting-started.md#installation|Installation]].\n",
    ),
    (
        "links_from_a_subdirectory",
        "docs/wiki/modules/ingestion.md",
        "Zurück zur [[../README.md|Übersicht]], weiter zur\n"
        "[[haystack-pipeline.md|Pipeline]].\n",
    ),
    (
        "links_to_repo_files",
        "docs/wiki/modules/docker-services.md",
        "Definiert in [[../../../docker-compose.yml|docker-compose.yml]] und\n"
        "[[../../../docker/setup.sh|setup.sh]].\n",
    ),
    (
        "unmapped_md_link_degrades_to_a_label",
        "docs/wiki/README.md",
        "Siehe [[notes/scratch.md|Notizen]] — kein konvertiertes Ziel.\n",
    ),
    (
        "bare_links_without_a_label",
        "docs/wiki/README.md",
        "[[architecture.md]] und [[modules/ingestion.md#chunking]] und\n"
        "[[../../docker-compose.yml]].\n",
    ),
    (
        "same_page_anchors_stay_wiki_links",
        "docs/wiki/modules/chatbot-extension.md",
        "Siehe [[#notable-patterns--gotchas|Notable Patterns]] und\n"
        "[[#konfiguration]] weiter unten.\n",
    ),
    (
        "plain_wiki_links_are_left_alone",
        "docs/wiki/README.md",
        "[[Hauptseite]] und [[Chatbot-FAQ]] bleiben unverändert,\n"
        "ebenso [[Help:Inhaltsverzeichnis]].\n",
    ),
]


@pytest.mark.parametrize("name,src,text", CASES, ids=[c[0] for c in CASES])
def test_postprocess_golden(assert_golden_text, name, src, text):
    assert_golden_text(name, cdp.postprocess(text, src, REPO_URL))


# ─── properties the golden files cannot state ───────────────────────
def test_mermaid_entities_are_decoded():
    """The failure this guards is a page of `--&gt;` instead of a diagram.

    Stated as an assertion as well as a golden file because the golden file
    would happily record the broken output if it were regenerated from a broken
    implementation, and this says what "correct" means.
    """
    out = cdp.postprocess(
        '<pre class="mermaid">A --&gt; B &amp; C</pre>', "docs/wiki/architecture.md"
    )
    assert "-->" in out and "--&gt;" not in out
    assert "&" in out and "&amp;" not in out


def test_mermaid_hexagon_shape_avoids_template_syntax():
    """`id{{"label"}}` must not survive into the page.

    MediaWiki expands `{{...}}` as a template *before* the Mermaid extension
    sees the content, so a hexagon node arrives as a corrupted graph. The
    substitution to the subroutine shape `[[...]]` is the whole reason this
    function is not a one-line unescape.
    """
    out = cdp.postprocess(
        '<pre class="mermaid">flow\n  n1{{"OpenSearch"}}\n</pre>',
        "docs/wiki/architecture.md",
    )
    assert '{{"OpenSearch"}}' not in out
    assert '[["OpenSearch"]]' in out
    # The parser-function call itself is the only {{ left.
    assert out.count("{{") == 1 and out.startswith("{{#mermaid:")


def test_same_page_anchor_is_not_turned_into_a_repo_link():
    """`[[#section|label]]` must survive untouched.

    It is already correct wikitext. Resolving it as a relative path yields the
    source file's *directory*, which is not a page and not a file — so it came
    out as an external link to `<repo>/docs/wiki/modules#section`, a link to a
    directory. Four of the eleven converted pages carried one of these.
    """
    out = cdp.postprocess(
        "See [[#notable-patterns--gotchas|the gotchas]].",
        "docs/wiki/modules/chatbot-extension.md",
        REPO_URL,
    )
    assert out == "See [[#notable-patterns--gotchas|the gotchas]]."


def test_every_mapped_source_exists(repo_root):
    """PAGE_MAP names markdown files that are actually in the repo."""
    missing = [src for src in cdp.PAGE_MAP if not (repo_root / src).is_file()]
    assert not missing, (
        f"PAGE_MAP names {len(missing)} source file(s) that do not exist: "
        f"{missing}. convert-docs.sh warns and skips these, so the "
        f"corresponding Help page silently stops being regenerated."
    )


def test_every_mapped_page_has_a_committed_output(repo_root):
    """Each target page has its converted .wiki file committed.

    setup.sh seeds `docker/mediawiki/wiki-docs/*.wiki`, not `docs/wiki/*.md`.
    A page in the map with no committed output is a doc that exists in the repo
    and never reaches the wiki.
    """
    out_dir = repo_root / "docker" / "mediawiki" / "wiki-docs"
    missing = []
    for src, page in cdp.PAGE_MAP.items():
        # convert-docs.sh encodes the subpage separator '/' as '__' on disk.
        if not (out_dir / f"{page.replace('/', '__')}.wiki").is_file():
            missing.append((src, page))
    assert not missing, (
        f"no committed wikitext for {missing}. Run scripts/convert-docs.sh "
        f"(needs the stack running) and commit the result."
    )


def test_page_for_and_list_sources_agree():
    """The CLI surface convert-docs.sh drives is consistent with the map."""
    assert cdp.page_for("docs/wiki/README.md") == "Help:Technische_Dokumentation"
    assert cdp.page_for("docs/wiki/does-not-exist.md") is None
    assert list(cdp.PAGE_MAP) == sorted(cdp.PAGE_MAP, key=list(cdp.PAGE_MAP).index)
