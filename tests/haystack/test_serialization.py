"""Tests for docker/haystack/serialization.py.

Real Haystack objects throughout. Nothing is mocked, because it does not need
to be: haystack-ai installs in 24s and 172MB with no torch and no transformers
(those arrive via sentence-transformers, which nothing here touches). A mocked
Document would test the mock, and the branches below are exactly the kind that
only a real object exercises — GeneratedAnswer has no `score` attribute and
ExtractedAnswer has no `documents` attribute, and to_native's behaviour turns
on both facts.

to_native exists so /hdp_pipeline/run can hand its result to JSONResponse. When
it misses a type the endpoint returns 500 on a query that actually succeeded,
which is the failure this file guards against.
"""
import json
from dataclasses import dataclass, field
from typing import Any

import numpy as np
import pytest
from haystack import Answer, Document, ExtractedAnswer, GeneratedAnswer, Pipeline
from serialization import load_pipeline, to_native

pytestmark = pytest.mark.haystack


# ─── to_native: Document ────────────────────────────────────────────────

def test_document_becomes_a_json_safe_dict():
    doc = Document(content="hello", meta={"title": "T"}, score=0.5)
    out = to_native(doc)
    assert out == {"content": "hello", "meta": {"title": "T"}, "id": doc.id, "score": 0.5}


def test_document_score_is_coerced_from_numpy():
    """OpenSearch retrieval returns numpy floats. json.dumps cannot serialize
    np.float64, so this coercion is what stops a successful query returning
    500."""
    doc = Document(content="c", meta={}, score=np.float64(0.25))
    out = to_native(doc)
    assert out["score"] == 0.25
    assert type(out["score"]) is float


def test_document_with_no_score_keeps_none():
    assert to_native(Document(content="c"))["score"] is None


def test_document_with_empty_meta_becomes_empty_dict():
    assert to_native(Document(content="c", meta={}))["meta"] == {}


def test_document_meta_is_converted_recursively():
    doc = Document(content="c", meta={"scores": [np.float32(1.5)], "n": np.int64(3)})
    assert to_native(doc)["meta"] == {"scores": [1.5], "n": 3}


def test_document_embedding_is_not_returned():
    """The embedding is a 1024-float vector on every document. Emitting it
    would multiply the response size for data the frontend never reads."""
    doc = Document(content="c", embedding=[0.1] * 1024)
    assert "embedding" not in to_native(doc)


# ─── to_native: answers ─────────────────────────────────────────────────

def test_generated_answer_becomes_a_json_safe_dict():
    doc = Document(content="src", meta={"title": "T"}, score=0.9)
    answer = GeneratedAnswer(data="the answer", query="the question",
                             documents=[doc], meta={"model": "x"})
    out = to_native(answer)
    assert out["answer"] == "the answer"
    assert out["query"] == "the question"
    assert out["meta"] == {"model": "x"}
    assert out["documents"] == [to_native(doc)]


def test_generated_answer_score_is_none_because_it_has_no_score_field():
    """GeneratedAnswer's fields are data, query, documents, meta — there is no
    score. to_native's hasattr guard is what keeps that from being an
    AttributeError on the RAG path, which is the only answer type the live
    pipeline produces."""
    answer = GeneratedAnswer(data="a", query="q", documents=[], meta={})
    assert not hasattr(answer, "score")
    assert to_native(answer)["score"] is None


def test_extracted_answer_keeps_its_score():
    doc = Document(content="src")
    answer = ExtractedAnswer(query="q", score=np.float64(0.75), data="a", document=doc,
                             context=None, document_offset=None, context_offset=None, meta={})
    out = to_native(answer)
    assert out["score"] == 0.75
    assert type(out["score"]) is float


def test_extracted_answer_drops_its_document():
    """Documents a real asymmetry. ExtractedAnswer has a singular `document`
    field, not `documents`, so to_native's `hasattr(obj, "documents")` guard is
    False and the source document is silently dropped from the response.

    This is latent rather than broken: hdp_pipeline.yaml's rag path produces
    GeneratedAnswer, never ExtractedAnswer, so nothing in production hits it.
    It is pinned here so that if an extractive reader is ever added to the
    pipeline, the missing citations are a red test rather than a support
    ticket about answers that cite nothing.
    """
    answer = ExtractedAnswer(query="q", score=0.5, data="a", document=Document(content="src"),
                             context=None, document_offset=None, context_offset=None, meta={})
    assert not hasattr(answer, "documents")
    assert to_native(answer)["documents"] == []


def test_answer_protocol_matches_structurally_not_by_inheritance():
    """`Answer` is a runtime_checkable Protocol, not a base class —
    GeneratedAnswer.__mro__ is [GeneratedAnswer, object]. So the third arm of
    to_native's isinstance tuple catches answer types that inherit nothing."""
    assert isinstance(GeneratedAnswer(data="a", query="q", documents=[], meta={}), Answer)

    @dataclass
    class CustomAnswer:
        data: Any = "custom"
        query: str = "q"
        meta: dict = field(default_factory=lambda: {"k": np.int64(1)})

        def to_dict(self):
            return {}

        @classmethod
        def from_dict(cls, d):
            return cls()

    assert isinstance(CustomAnswer(), Answer)
    out = to_native(CustomAnswer())
    assert out["answer"] == "custom"
    assert out["meta"] == {"k": 1}
    assert out["documents"] == []
    assert out["score"] is None


def test_answer_protocol_requires_an_actual_dataclass():
    """Non-obvious, and worth pinning because it silently narrows the third arm.

    Answer is declared as `@runtime_checkable @dataclass class Answer(Protocol)`.
    The @dataclass decorator puts __dataclass_fields__ into the attribute set
    isinstance() checks, so a plain class carrying data, query, meta, to_dict
    and from_dict — everything the protocol body actually declares — still does
    not match.

    The consequence: a future answer type that is not a dataclass falls past
    all three arms and past the dict/list arms, and to_native returns the object
    unchanged. json.dumps then raises and a working query becomes a 500.
    """
    class NotADataclass:
        data = "d"
        query = "q"
        meta: dict = {}

        def to_dict(self):
            return {}

        @classmethod
        def from_dict(cls, d):
            return cls()

    obj = NotADataclass()
    assert not hasattr(NotADataclass, "__dataclass_fields__")
    assert not isinstance(obj, Answer)
    assert to_native(obj) is obj
    with pytest.raises(TypeError):
        json.dumps(to_native(obj))


def test_a_document_is_not_treated_as_an_answer():
    """Document is checked first, and does not satisfy the Answer protocol
    anyway. If that ordering ever flipped, every retrieved document would come
    back shaped like an answer."""
    assert not isinstance(Document(content="c"), Answer)
    assert "content" in to_native(Document(content="c"))


# ─── to_native: containers and numpy scalars ────────────────────────────

def test_dicts_are_converted_recursively():
    assert to_native({"a": {"b": np.int64(1)}}) == {"a": {"b": 1}}


def test_lists_are_converted_recursively():
    assert to_native([np.int64(1), [np.float32(2.5)]]) == [1, [2.5]]


def test_tuples_become_lists():
    """json has no tuple. Returning one would fail serialization, so the
    conversion to list is required, not incidental."""
    out = to_native((np.int64(1), "x"))
    assert out == [1, "x"]
    assert isinstance(out, list)


@pytest.mark.parametrize("value,expected", [
    (np.int8(1), 1), (np.int32(2), 2), (np.int64(3), 3), (np.uint16(4), 4),
    (np.float16(1.5), 1.5), (np.float32(2.5), 2.5), (np.float64(3.5), 3.5),
])
def test_numpy_scalars_are_coerced(value, expected):
    out = to_native(value)
    assert out == expected
    assert type(out) in (int, float)


@pytest.mark.parametrize("array,expected", [
    (np.array([1, 2, 3]), [1, 2, 3]),
    (np.array([[1.5, 2.5], [3.5, 4.5]]), [[1.5, 2.5], [3.5, 4.5]]),
    (np.array([]), []),
])
def test_ndarrays_become_nested_lists(array, expected):
    out = to_native(array)
    assert isinstance(out, list)
    assert out == expected


@pytest.mark.parametrize("value", ["str", 7, 1.5, True, None, b"bytes"])
def test_unrecognised_values_pass_through_unchanged(value):
    assert to_native(value) is value


def test_numpy_bool_is_not_converted():
    """np.bool_ is not one of np.integer, np.floating or np.ndarray, so it
    falls through untouched — and json.dumps cannot serialize it.

    Nothing in the pipeline currently puts a np.bool_ in a response, which is
    why this has never fired. It is pinned as a known gap rather than fixed:
    adding the branch is a behaviour change and belongs with the integration
    tests in Wave 3, not in a commit whose job is to make the current
    behaviour visible.
    """
    out = to_native(np.bool_(True))
    assert isinstance(out, np.bool_)
    with pytest.raises(TypeError):
        json.dumps(out)


# ─── to_native: the actual contract ─────────────────────────────────────

def test_a_full_pipeline_shaped_result_is_json_serializable():
    """The reason this function exists. /hdp_pipeline/run hands to_native's
    output straight to JSONResponse, so anything that survives json.dumps here
    is a 200 and anything that does not is a 500 on a query that worked."""
    result = {
        "answer_joiner": {
            "answers": [
                GeneratedAnswer(
                    data="reformulated question", query="q", documents=[], meta={},
                ),
                GeneratedAnswer(
                    data="the real answer", query="q",
                    documents=[
                        Document(content="src", meta={"page_id": np.int64(7),
                                                      "vec": np.array([0.1, 0.2])},
                                 score=np.float64(0.87)),
                    ],
                    meta={"usage": {"tokens": np.int32(120)}},
                ),
            ]
        },
        "retriever": {"documents": (Document(content="d1"), Document(content="d2"))},
    }
    encoded = json.dumps(to_native(result))
    assert "the real answer" in encoded
    assert json.loads(encoded)["answer_joiner"]["answers"][1]["documents"][0]["score"] == 0.87


# ─── load_pipeline ──────────────────────────────────────────────────────

def test_load_pipeline_returns_a_pipeline_and_no_error(tmp_path):
    """`{}` is a valid empty pipeline — no components, no API keys, no network."""
    f = tmp_path / "p.yaml"
    f.write_text("{}")
    pipeline, error = load_pipeline(str(f))
    assert isinstance(pipeline, Pipeline)
    assert error is None


def test_load_pipeline_degrades_instead_of_raising_on_bad_yaml(tmp_path):
    """The Wave 0 contract. A pipeline that will not deserialize must leave the
    process alive with the reason recorded, so /health can stay 200 and /ready
    can return 503 — rather than taking the container down and making the
    documented .env-only dev path unusable."""
    f = tmp_path / "p.yaml"
    f.write_text("not: [valid")
    pipeline, error = load_pipeline(str(f))
    assert pipeline is None
    assert isinstance(error, str) and error


def test_load_pipeline_runs_the_yaml_through_envsubst(tmp_path, monkeypatch):
    """envsubst is what turns ${HDP_EMBEDDING_MODEL} and friends into values.
    If it were skipped, the literal `${...}` text would reach Pipeline.loads."""
    monkeypatch.setenv("HDP_TEST_SUBSTITUTION", "substituted-value")
    f = tmp_path / "p.yaml"
    f.write_text("metadata:\n  probe: ${HDP_TEST_SUBSTITUTION}\n")
    pipeline, error = load_pipeline(str(f))
    assert error is None
    assert pipeline.metadata["probe"] == "substituted-value"


def test_load_pipeline_blanks_unset_variables(tmp_path, monkeypatch):
    """envsubst substitutes an unset variable with the empty string. That is
    how a missing HDP_LLM_API_KEY reaches Pipeline.loads as an empty value
    rather than as the literal `${HDP_LLM_API_KEY}`."""
    monkeypatch.delenv("HDP_DEFINITELY_UNSET", raising=False)
    f = tmp_path / "p.yaml"
    f.write_text("metadata:\n  probe: 'x${HDP_DEFINITELY_UNSET}y'\n")
    pipeline, error = load_pipeline(str(f))
    assert error is None
    assert pipeline.metadata["probe"] == "xy"


def test_load_pipeline_raises_on_a_missing_file(tmp_path):
    """Deliberately not caught. A pipeline file that cannot be read is a broken
    install, not a degraded configuration, and there is no useful degraded mode
    to offer — see the docstring on load_pipeline. Failing at container start
    beats a confusing 503 on every query."""
    with pytest.raises(FileNotFoundError):
        load_pipeline(str(tmp_path / "does-not-exist.yaml"))


def test_the_real_pipeline_yaml_degrades_rather_than_raising(repo_root, tmp_path):
    """The shipped hdp_pipeline.yaml, loaded without a configured LLM key.

    The contract under test is "degrade, do not raise", which is what keeps the
    haystack container healthy on an install with no API key. Note the failure
    reason differs by environment: in this tier the deserialization fails on a
    component class that is not installed (sentence-transformers is
    deliberately absent), while in production it fails on OpenAIGenerator
    refusing to construct without HDP_LLM_API_KEY. Same contract, and this
    asserts the contract rather than the message.
    """
    src = (repo_root / "docker" / "haystack" / "hdp_pipeline.yaml").read_text()
    f = tmp_path / "hdp_pipeline.yaml"
    f.write_text(src)

    pipeline, error = load_pipeline(str(f))
    assert (pipeline is None) != (error is None), "exactly one of pipeline/error must be set"
    if pipeline is None:
        assert isinstance(error, str) and error
