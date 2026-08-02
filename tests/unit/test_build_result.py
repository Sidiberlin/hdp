"""Golden-file tests for build_result_from_haystack in docker/chatbot-proxy/server.py.

This function is the seam between two APIs the project does not control: what
hayhooks returns for hdp_pipeline.yaml, and the Deepset-shaped payload the
ChatBot MediaWiki extension's frontend expects. Both sides can move
independently, and neither move announces itself — the proxy will happily emit
a well-formed response with the wrong fields in it, and the user sees a chat
answer that is empty, unattributed, or literally the string "{}".

Golden files rather than hand-written assertions because the payload is nested
and the whole shape matters. A field quietly disappearing from `meta` is the
failure mode here, and per-field assertions only catch the fields someone
thought to assert.

Regenerate with: scripts/ci/pytest.sh --regen-golden   (then review the diff)

Standard library only — server.py imports nothing else.
"""
import json

import pytest
from server import build_result_from_haystack

QUERY = "Was sind Cloud-Computing-Modelle?"


def doc(i, **overrides):
    """A retrieved document in the shape ingest_hdp_wiki.py writes to OpenSearch."""
    base = {
        "id": f"doc-{i}",
        "content": f"Inhalt von Dokument {i}.",
        "meta": {
            "prefixed_title": f"Help:Seite {i}",
            "uri": f"http://mediawiki-web:8080/w/Help:Seite_{i}",
            "namespace": 12,
            "page_id": 100 + i,
            "title_level_1": f"Help:Seite {i}",
        },
    }
    base.update(overrides)
    return base


def answer(text, documents=None, **overrides):
    base = {"answer": text}
    if documents is not None:
        base["documents"] = documents
    base.update(overrides)
    return base


# Every case is (name, hayhooks response). The name is also the golden filename.
CASES = {
    # The normal path: answer_joiner returns the reformulated query at [0] and
    # the real answer at [-1], carrying the documents it was grounded in.
    "two_answers": {
        "answer_joiner": {"answers": [
            answer("Umformulierte Frage"),
            answer("IaaS, PaaS und SaaS.", documents=[doc(1), doc(2)]),
        ]}
    },

    # No answers at all. The German fallback string is what the user reads.
    "empty_answers": {"answer_joiner": {"answers": []}},

    # A single answer is the reformulated query with no real answer behind it.
    "single_answer": {"answer_joiner": {"answers": [answer("Nur die Frage")]}},

    # An answer with no documents attached and none at top level: the chat
    # answer renders with no citations.
    "missing_documents": {
        "answer_joiner": {"answers": [answer("Frage"), answer("Antwort ohne Quellen")]}
    },

    # hayhooks wraps the payload under "result" in some versions.
    "hayhooks_result_wrapper": {"result": {
        "answer_joiner": {"answers": [
            answer("Frage"), answer("Eingepackte Antwort", documents=[doc(1)]),
        ]}
    }},

    # answer_joiner present but empty: falsy, so the top-level "answers" key is
    # used instead. This fallback is what keeps a pipeline without an
    # answer_joiner component working.
    "empty_answer_joiner": {
        "answer_joiner": {},
        "answers": [answer("Frage"), answer("Antwort ohne Joiner", documents=[doc(3)])],
    },

    # No answer-level documents, but the pipeline put them at top level.
    "documents_from_top_level": {
        "answers": [answer("Frage"), answer("Antwort")],
        "documents": [doc(4), doc(5)],
    },

    # The second top-level fallback key.
    "documents_from_retrieved_documents": {
        "answers": [answer("Frage"), answer("Antwort")],
        "retrieved_documents": [doc(6)],
    },

    # Twelve documents; docs[:10] caps the list at ten.
    "documents_capped_at_ten": {
        "answer_joiner": {"answers": [
            answer("Frage"),
            answer("Viele Quellen", documents=[doc(i) for i in range(1, 13)]),
        ]}
    },

    # content[:200] truncates. 250 characters in, 200 out.
    "content_truncated_at_200": {
        "answer_joiner": {"answers": [
            answer("Frage"),
            answer("Langer Inhalt", documents=[doc(1, content="x" * 250)]),
        ]}
    },

    # A document with no "meta" key: `doc.get("meta", doc)` falls back to the
    # document itself, so top-level keys are read as metadata.
    "document_without_meta": {
        "answer_joiner": {"answers": [
            answer("Frage"),
            answer("Antwort", documents=[
                {"id": "flat-1", "content": "flach", "prefixed_title": "Help:Flach",
                 "uri": "http://mediawiki-web:8080/w/Help:Flach"},
            ]),
        ]}
    },

    # A document whose meta has none of the three title keys.
    "document_without_title": {
        "answer_joiner": {"answers": [
            answer("Frage"),
            answer("Antwort", documents=[{"id": "d", "content": "c", "meta": {}}]),
        ]}
    },

    # Entries in "answers" that are not dicts at all.
    "non_dict_answer_entries": {"answer_joiner": {"answers": ["nur ein String"]}},
    "non_dict_answer_entries_pair": {
        "answer_joiner": {"answers": ["Frage", ["auch", "kein", "dict"]]}
    },

    # A document that is not a dict.
    "non_dict_document": {
        "answer_joiner": {"answers": [
            answer("Frage"), answer("Antwort", documents=["kein dict"]),
        ]}
    },

    # An upstream error payload. call_hayhooks returns this shape on HTTPError.
    "hayhooks_error_payload": {"error": "connection refused", "status": 502},
}


@pytest.mark.parametrize("name", sorted(CASES))
def test_build_result_matches_golden(name, assert_golden):
    assert_golden(name, build_result_from_haystack(CASES[name], QUERY))


# ─── Properties that must hold for every case ───────────────────────────
# The golden files pin the exact bytes. These pin the invariants the frontend
# depends on, so a regenerated golden cannot quietly relax one of them.

@pytest.mark.parametrize("name", sorted(CASES))
def test_result_is_json_serializable(name):
    json.dumps(build_result_from_haystack(CASES[name], QUERY))


@pytest.mark.parametrize("name", sorted(CASES))
def test_answers_zero_is_always_the_query(name):
    """Connector.php reads answers[0] as the reformulated question and
    answers[1] as the answer. Both must exist for every input, including the
    error payload — the frontend indexes into them without checking."""
    result = build_result_from_haystack(CASES[name], QUERY)
    assert len(result["answers"]) == 2
    assert result["answers"][0] == {"answer": QUERY}
    assert result["query"] == QUERY


@pytest.mark.parametrize("name", sorted(CASES))
def test_doc_ids_match_the_documents(name):
    """meta.doc_ids and meta.documents are read separately by the frontend; if
    they disagree, a citation points at a document that is not in the list."""
    result = build_result_from_haystack(CASES[name], QUERY)
    answer_meta = result["answers"][1]["meta"]
    assert answer_meta["doc_ids"] == [d["id"] for d in answer_meta["documents"]]
    assert answer_meta["documents"] == result["documents"]


@pytest.mark.parametrize("name", sorted(CASES))
def test_never_more_than_ten_documents(name):
    assert len(build_result_from_haystack(CASES[name], QUERY)["documents"]) <= 10


@pytest.mark.parametrize("name", sorted(CASES))
def test_document_content_is_never_longer_than_200_chars(name):
    for d in build_result_from_haystack(CASES[name], QUERY)["documents"]:
        assert len(d["content"]) <= 200


@pytest.mark.parametrize("name", sorted(CASES))
def test_result_and_query_ids_are_distinct_uuid4s(name):
    """Two separate uuid4() calls. If they were ever collapsed into one value,
    the frontend's result and query correlation would silently merge."""
    a = build_result_from_haystack(CASES[name], QUERY)["answers"][1]
    assert a["result_id"] != a["query_id"]


def test_ids_differ_between_calls():
    """Freshly minted per call, not module-level constants."""
    first = build_result_from_haystack(CASES["two_answers"], QUERY)["answers"][1]
    second = build_result_from_haystack(CASES["two_answers"], QUERY)["answers"][1]
    assert first["result_id"] != second["result_id"]


# ─── Specific behaviours worth naming ───────────────────────────────────

def test_empty_answers_produce_the_german_fallback():
    result = build_result_from_haystack(CASES["empty_answers"], QUERY)
    assert result["answers"][1]["answer"] == "Es konnte keine Antwort gefunden werden."


def test_untitled_documents_fall_back_to_unbekannt():
    result = build_result_from_haystack(CASES["document_without_title"], QUERY)
    assert result["documents"][0]["meta"]["prefixed_title"] == "Unbekannt"


def test_a_non_dict_answer_renders_as_the_literal_string_of_an_empty_dict():
    """Documents a real defect. Do not "fix" this without Wave 3.

    When an entry in `answers` is not a dict, the code substitutes `{}` and
    then falls through `first.get("answer", "") or first.get("reply", "") or
    str(first)`. The first two yield "", so `str({})` wins and the user is
    shown the literal two-character string "{}" as the chatbot's answer.

    The `if not answer_text` fallback below it cannot help, because "{}" is
    truthy. The German "no answer found" message — which is what should appear
    here — is unreachable for this input.

    Nothing produces this today: hdp_pipeline.yaml's answer_joiner emits
    GeneratedAnswer objects, which hdp_api_server.to_native turns into dicts.
    It would take an upstream shape change to reach it, and that is exactly the
    kind of change nobody notices until a user reports a nonsense answer.
    Pinned so the change is visible; fixing it is a behaviour change and
    belongs with the integration tests in Wave 3.
    """
    for name in ("non_dict_answer_entries", "non_dict_answer_entries_pair"):
        result = build_result_from_haystack(CASES[name], QUERY)
        assert result["answers"][1]["answer"] == "{}", (
            "a non-dict answer entry no longer renders as '{}'. If that was "
            "fixed deliberately, update this test; the expected answer is the "
            "German fallback string."
        )


def test_error_payload_still_produces_a_well_formed_result():
    """call_hayhooks returns {"error": ..., "status": ...} when hayhooks is
    unreachable. The proxy must still emit a shape the frontend can parse,
    rather than raising and turning a 503 into a stack trace."""
    result = build_result_from_haystack(CASES["hayhooks_error_payload"], QUERY)
    assert result["answers"][1]["answer"] == "Es konnte keine Antwort gefunden werden."
    assert result["documents"] == []


def test_the_result_wrapper_is_unwrapped():
    """Same input, wrapped and unwrapped, must produce the same answer text."""
    wrapped = build_result_from_haystack(CASES["hayhooks_result_wrapper"], QUERY)
    inner = build_result_from_haystack(CASES["hayhooks_result_wrapper"]["result"], QUERY)
    assert wrapped["answers"][1]["answer"] == inner["answers"][1]["answer"]
    assert wrapped["documents"] == inner["documents"]
