"""Tests for docker/haystack/render_pipeline.py.

render() performs the one substitution envsubst cannot: swapping the query-time
embedder's Python *class* between SentenceTransformersTextEmbedder (local, CPU)
and OpenAITextEmbedder (remote HTTP). Getting it wrong does not fail loudly —
it produces a YAML file that hayhooks then rejects at container start, which
surfaces as an unhealthy haystack container rather than as a rendering error.

Standard library only. No haystack-ai, no envsubst, no container.
"""
import re

import pytest
from render_pipeline import (
    BLOCKS,
    LOCAL_BLOCK,
    REMOTE_BLOCK,
    SENTINEL_END,
    SENTINEL_START,
    render,
)

# A minimal stand-in for the real YAML: enough structure to prove the block is
# swapped and its surroundings are not. The real file is exercised separately
# below, because a synthetic fixture cannot catch the real one drifting.
SYNTHETIC = f"""\
components:
  before_marker:
    type: some.module.Before
{SENTINEL_START}
  query_embedder:
    type: PLACEHOLDER
{SENTINEL_END}
  after_marker:
    type: some.module.After
"""


def _embedder_type(rendered: str) -> str:
    """The `type:` line of the rendered query_embedder component."""
    match = re.search(r"^\s*type:\s*(\S+)$", rendered.split("query_embedder:")[1], re.MULTILINE)
    assert match, f"no component type found after query_embedder in:\n{rendered}"
    return match.group(1)


# ─── The six cases named in the Wave 2 plan ─────────────────────────────

def test_local_substitutes_the_local_block():
    out = render(SYNTHETIC, "local")
    assert LOCAL_BLOCK in out
    assert REMOTE_BLOCK not in out
    assert "PLACEHOLDER" not in out
    assert _embedder_type(out).endswith("SentenceTransformersTextEmbedder")


def test_remote_substitutes_the_remote_block():
    out = render(SYNTHETIC, "remote")
    assert REMOTE_BLOCK in out
    assert LOCAL_BLOCK not in out
    assert "PLACEHOLDER" not in out
    assert _embedder_type(out).endswith("OpenAITextEmbedder")


def test_invalid_provider_exits_and_names_the_valid_ones():
    with pytest.raises(SystemExit) as excinfo:
        render(SYNTHETIC, "nonsense")
    message = str(excinfo.value)
    assert "nonsense" in message
    for valid in BLOCKS:
        assert valid in message


def test_hf_space_is_rejected_with_the_ingestion_only_explanation():
    """hf_space is valid for ingest_hdp_wiki.py but never for the live pipeline.

    The message has to say so, otherwise an operator who set
    HDP_EMBEDDING_PROVIDER=hf_space (a documented value elsewhere) gets told
    only that it is invalid, with no hint that the value is real but scoped to
    a different script.
    """
    with pytest.raises(SystemExit) as excinfo:
        render(SYNTHETIC, "hf_space")
    message = str(excinfo.value)
    assert "hf_space" in message
    assert "ingest_hdp_wiki.py" in message


def test_missing_sentinels_exits():
    with pytest.raises(SystemExit) as excinfo:
        render("components:\n  nothing_here: {}\n", "local")
    assert "sentinel" in str(excinfo.value).lower()


def test_unterminated_block_exits():
    """START present, END missing — the regex cannot match, so this must exit.

    A silent pass-through here would ship the YAML with its PLACEHOLDER intact.
    """
    truncated = SYNTHETIC.split(SENTINEL_END)[0]
    assert SENTINEL_START in truncated and SENTINEL_END not in truncated
    with pytest.raises(SystemExit):
        render(truncated, "local")


def test_double_sentinels_replace_only_the_first():
    """subn(..., count=1) is load-bearing, not incidental.

    Two blocks means a hand-edited file. Replacing both would silently
    "fix" it; replacing one leaves the second block visible so the mistake
    is diagnosable.
    """
    doubled = SYNTHETIC + "\n" + SYNTHETIC
    out = render(doubled, "local")
    assert out.count(LOCAL_BLOCK) == 1
    assert out.count("PLACEHOLDER") == 1


def test_render_is_idempotent():
    once = render(SYNTHETIC, "local")
    twice = render(once, "local")
    assert once == twice


# ─── Beyond the six: properties the docstring claims ────────────────────

def test_only_the_block_changes():
    """render_pipeline.py's docstring: "Every other line in the file is left
    byte-identical." That is a contract worth pinning."""
    out = render(SYNTHETIC, "remote")
    before, _, after = SYNTHETIC.partition(SENTINEL_START)
    out_before, _, _ = out.partition(SENTINEL_START)
    assert out_before == before
    assert out.endswith(after.split(SENTINEL_END, 1)[1])


def test_switching_provider_and_back_returns_the_original_rendering():
    """local -> remote -> local must land exactly where local alone lands.

    entrypoint.sh re-renders in place on every container start, so a provider
    change followed by a change back is a real sequence, not a hypothetical.
    """
    local_once = render(SYNTHETIC, "local")
    round_trip = render(render(local_once, "remote"), "local")
    assert round_trip == local_once


# ─── Against the file that actually ships ───────────────────────────────

@pytest.mark.parametrize("provider", sorted(BLOCKS))
def test_the_real_pipeline_yaml_renders(pipeline_yaml, provider):
    """The synthetic fixture cannot catch hdp_pipeline.yaml drifting away from
    the sentinels render_pipeline.py looks for. This can."""
    out = render(pipeline_yaml, provider)
    assert BLOCKS[provider] in out
    assert out != pipeline_yaml


def test_the_real_pipeline_yaml_has_exactly_one_block(pipeline_yaml):
    assert pipeline_yaml.count(SENTINEL_START) == 1
    assert pipeline_yaml.count(SENTINEL_END) == 1
