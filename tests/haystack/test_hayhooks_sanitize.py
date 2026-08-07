"""Tests for sanitize_result() in docker/haystack/hayhooks_numpy_patch.py.

Real Haystack objects, same reasoning as test_serialization.py: haystack-ai
installs in 24s with no torch, and the branches that matter here are exactly
the ones a mock would paper over — that `Document` is a dataclass, that
`GeneratedAnswer.documents` holds more dataclasses, and that the `score` a
retriever writes is a numpy scalar rather than a float.

The bug this guards against is the one found end to end on the GPU box: the
pipeline runs, hayhooks tries to Pydantic-serialize the result, and the client
gets a 500 for a query that actually succeeded. So every test here ends at
json.dumps — "is it a float now" is a proxy, "does it serialize" is the thing.

The other half of the module, patch_source(), is standard-library-only and is
covered in tests/unit/test_hayhooks_numpy_patch.py. The two files are named
apart rather than both after the module: the tiers have no __init__.py, so
pytest resolves test modules by basename and a shared one is a collection error.
"""
import json
from dataclasses import dataclass

import numpy as np
import pytest
from hayhooks_numpy_patch import sanitize_result
from haystack import Document, ExtractedAnswer, GeneratedAnswer

pytestmark = pytest.mark.haystack


def _roundtrip(obj):
    """Sanitize, then prove the result survives JSON. Returns the decoded value."""
    return json.loads(json.dumps(sanitize_result(obj)))


# ─── the reported failure ───────────────────────────────────────────────

def test_numpy_score_on_a_document_becomes_json_safe():
    # np.float32 is what OpenSearch's retriever and the cross-encoder ranker
    # put in Document.score, and the exact type in the PydanticSerializationError.
    doc = Document(content="hallo", meta={"title": "T"}, score=np.float32(0.87))

    result = _roundtrip(doc)

    assert isinstance(result["score"], float)
    assert result["score"] == pytest.approx(0.87, rel=1e-6)
    assert result["content"] == "hallo"
    assert result["meta"] == {"title": "T"}


def test_a_full_pipeline_result_shape_survives():
    # What /hdp_pipeline/run actually returns: a dict keyed by component name,
    # holding lists of Haystack dataclasses with numpy scores several levels
    # down. Nothing here is reachable by a top-level isinstance check.
    result = {
        "answer_builder": {
            "answers": [
                GeneratedAnswer(
                    data="Die Antwort.",
                    query="Was ist HDP?",
                    documents=[Document(content="a", score=np.float32(0.9))],
                    meta={"model": "gpt-4o", "confidence": np.float64(0.42)},
                )
            ]
        },
        "ranker": {"documents": [Document(content="b", score=np.float32(0.1))]},
    }

    decoded = _roundtrip(result)

    answer = decoded["answer_builder"]["answers"][0]
    assert answer["data"] == "Die Antwort."
    assert isinstance(answer["documents"][0]["score"], float)
    assert isinstance(answer["meta"]["confidence"], float)
    assert isinstance(decoded["ranker"]["documents"][0]["score"], float)


def test_extracted_answer_is_handled_too():
    # ExtractedAnswer has a `score` field of its own and no `documents`, unlike
    # GeneratedAnswer. sanitize_result() is structural so it does not care, but
    # to_native() in serialization.py needed both branches spelled out — this
    # asserts the structural version really does cover the same ground.
    answer = ExtractedAnswer(
        query="q",
        score=np.float32(0.5),
        data="d",
        document=Document(content="c", score=np.float32(0.25)),
        context=None,
        document_offset=None,
        context_offset=None,
        meta={},
    )

    decoded = _roundtrip(answer)

    assert isinstance(decoded["score"], float)
    assert isinstance(decoded["document"]["score"], float)


# ─── numpy coverage ─────────────────────────────────────────────────────

@pytest.mark.parametrize(
    "value",
    [
        np.float16(1.5), np.float32(1.5), np.float64(1.5),
        np.int8(3), np.int16(3), np.int32(3), np.int64(3),
        np.uint8(3), np.uint64(3),
        np.bool_(True),
    ],
    ids=lambda v: type(v).__name__,
)
def test_every_numpy_scalar_becomes_a_native_python_scalar(value):
    # Includes uint8/uint64, which the hand-written type list this replaced did
    # not mention: np.generic covers the whole family, so a dtype nobody
    # thought to enumerate cannot slip past.
    out = sanitize_result(value)

    assert type(out).__module__ == "builtins"
    json.dumps(out)


def test_ndarray_becomes_a_list():
    assert _roundtrip(np.array([[1.5, 2.5], [3.5, 4.5]], dtype=np.float32)) == [[1.5, 2.5], [3.5, 4.5]]


def test_object_dtype_array_elements_are_sanitized_too():
    # tolist() on an object-dtype array hands back the objects untouched, which
    # is why sanitize_result recurses into the list instead of returning it.
    array = np.empty(2, dtype=object)
    array[0] = np.float32(0.5)
    array[1] = Document(content="x", score=np.float32(0.25))

    decoded = _roundtrip(array)

    assert decoded[0] == pytest.approx(0.5)
    assert isinstance(decoded[1]["score"], float)


# ─── leaving well alone ─────────────────────────────────────────────────

@pytest.mark.parametrize("value", ["text", 3, 1.5, True, None])
def test_native_scalars_pass_through_unchanged(value):
    # The __dict__ fallback at the bottom of sanitize_result would turn a str
    # into something unrecognisable without its type guard. `is` rather than
    # `==` so a bool coming back as 1 would fail.
    assert sanitize_result(value) is value


def test_nested_containers_keep_their_shape():
    assert _roundtrip({"a": [1, {"b": (2, 3)}]}) == {"a": [1, {"b": [2, 3]}]}


def test_a_to_dict_that_raises_falls_through_instead_of_propagating():
    # Haystack's ChatMessage and ByteStream are reached through to_dict(), and
    # a component output whose to_dict() is broken should degrade to the
    # generic handling rather than turn a successful query into a 500 — which
    # is the same failure this whole module exists to remove.
    class Awkward:
        def __init__(self):
            self.score = np.float32(0.75)

        def to_dict(self):
            raise RuntimeError("not serializable")

    assert _roundtrip(Awkward()) == {"score": pytest.approx(0.75)}


def test_a_plain_dataclass_is_converted_without_haystack_knowing_it():
    @dataclass
    class Custom:
        name: str
        weight: np.float32

    decoded = _roundtrip(Custom(name="n", weight=np.float32(0.125)))

    assert decoded == {"name": "n", "weight": 0.125}
