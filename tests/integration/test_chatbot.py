"""4.4 — the chatbot leg. Uncovered by T3, because T3 runs neither container.

Two services, three ports, and none of them reachable the way the rest of this
tier reaches things:

  haystack        1416  hayhooks admin API — deliberately not asserted here
                  1417  hdp_api_server.py: /health, /ready, the RAG query API
  chatbot-proxy   8080  the Deepset-shaped adapter the wiki's ChatBot talks to:
                        GET /, POST /session, POST /chat-stream

`chatbot-proxy` publishes **no port at all** — it is reached only over the
compose network by the wiki — and its image is `python:3.12-slim`, which has
no curl, no wget and no nc. So every assertion below goes through
`docker compose exec` and the container's own python3. That is what the Wave 4
brief means by "compose exec for the proxy", and it is a property worth
keeping: a proxy that became reachable from the host would be an unauthenticated
RAG endpoint bypassing the wiki login.

haystack's two ports *are* published, but only on 127.0.0.1 and only because
the compose file binds them there for debugging. Asserting through the
container keeps this file working when HDP_BIND_ADDR is changed, and keeps all
four probes shaped the same way.

**The contract without an LLM key is the interesting one**, because it is the
one CI always exercises and the one Wave 0 Issue 2 was about:

    /health      200  + status "degraded"    liveness, so the container is healthy
    /ready       503                          readiness, the pipeline is not loaded
    /chat-stream 503                          NOT 200 carrying an error frame
    /session     200                          session creation never needs the LLM

The `/chat-stream` status code is the whole of Issue 2. The wiki's ChatBot
consumes that endpoint with `EventSource`, whose `onmessage` handler branches
on `type === 'delta'` and `type === 'result'` and has no branch for
`type === 'error'`. A 200 carrying an error frame was therefore *silently
discarded*: the promise never settled and the chat UI hung with no message at
all. `EventSource.onerror`, which fires on a non-200, is the only path that
surfaces a failure to a user. So the status code here is not a CI nicety; it is
the entire user-visible error path.

With a key configured — the box, never CI — the same endpoints must flip to
`/ready` 200 and a `/chat-stream` that really streams. Both sets live in this
file and the fixture picks by inspecting the container, so neither contract can
be quietly lost: whichever one does not apply is asserted *not* to hold.
"""
import json

import pytest

pytestmark = pytest.mark.smoke

# The RAG API port inside the haystack container. docker-compose.yml threads
# HDP_PDF_PORT into the container environment, the healthcheck and
# chatbot-proxy's HAYHOOKS_URL, so this default matches all three.
DEFAULT_HAYSTACK_API_PORT = "1417"

# chatbot-proxy binds this inside its container (CHATBOT_PROXY_PORT). Not
# published to the host, on purpose.
PROXY_PORT = "8080"

# A question with an answer in the seeded Help pages, for the end-to-end leg.
QUESTION = "Was ist die Architektur der HDP-Plattform?"


@pytest.fixture(scope="session")
def haystack_api(full_stack, dotenv, http_in):
    """Probe the haystack RAG API from inside its own container."""
    port = dotenv.get("HDP_PDF_PORT") or DEFAULT_HAYSTACK_API_PORT

    def _get(path, method="GET", payload=""):
        return http_in(
            "haystack", f"http://localhost:{port}{path}", method=method, payload=payload
        )

    return _get


@pytest.fixture(scope="session")
def proxy(full_stack, http_in):
    """Probe chatbot-proxy from inside its own container."""

    def _request(path, method="GET", payload=""):
        return http_in(
            "chatbot-proxy",
            f"http://localhost:{PROXY_PORT}{path}",
            method=method,
            payload=payload,
        )

    return _request


@pytest.fixture(scope="session")
def proxy_stream(full_stack, sse_in):
    """Consume a streaming response from chatbot-proxy, EventSource-style."""

    def _stream(path, payload, budget=240):
        return sse_in(
            "chatbot-proxy", f"http://localhost:{PROXY_PORT}{path}", payload, budget=budget
        )

    return _stream


@pytest.fixture(scope="session")
def llm_key_configured(full_stack, compose):
    """Whether the running haystack container has an LLM API key.

    Read from the container rather than from `.env`, because Infisical
    overwrites the value at container start when it is configured — so `.env`
    is not the source of truth for what the running process has. The key itself
    is never read out: the probe is a test for emptiness inside the container
    and what crosses the boundary is the string "yes" or "no".
    """
    proc = compose(
        "exec", "-T", "haystack", "sh", "-c",
        'if [ -n "$HDP_LLM_API_KEY" ]; then echo yes; else echo no; fi',
        timeout=120,
    )
    assert proc.returncode == 0, (
        f"could not inspect the haystack container's environment "
        f"(exit {proc.returncode})\n{proc.stderr[-2000:]}"
    )
    return proc.stdout.strip().endswith("yes")


# ─── liveness and readiness ─────────────────────────────────────────
def test_health_is_liveness_and_answers_200(haystack_api):
    """`/health` is 200 whenever the process is up, key or no key.

    This is the endpoint docker-compose polls. Tying it to pipeline state is
    what made the haystack container permanently unhealthy on every install
    without a paid LLM key — /health 503'd forever, the container never went
    healthy, and no CI gate asserting seven green healthchecks could ever pass.
    That is Wave 0 Issue 2, and this assertion is what stops it coming back.
    """
    status, body = haystack_api("/health")
    assert status == 200, (
        f"/health returned HTTP {status}, not 200. Liveness must not depend on "
        f"the RAG pipeline being loaded — see docker/haystack/hdp_api_server.py."
        f"\n{body[:600]}"
    )
    payload = json.loads(body)
    assert payload.get("status") in ("ok", "degraded"), payload
    assert "pipeline_loaded" in payload, (
        f"/health does not report whether the pipeline is loaded: {payload!r}. "
        f"Degradation has to stay visible somewhere once /health stopped "
        f"failing for it."
    )


def test_health_reports_degraded_exactly_when_the_pipeline_is_unloaded(
    haystack_api, llm_key_configured
):
    """The body tells the truth about the pipeline, in both directions."""
    _, body = haystack_api("/health")
    payload = json.loads(body)
    if llm_key_configured:
        assert payload["pipeline_loaded"] is True, (
            f"HDP_LLM_API_KEY is set in the haystack container but the "
            f"pipeline did not load: {payload!r}. Check "
            f"`docker compose logs haystack` for the deploy result."
        )
        assert payload["status"] == "ok", payload
    else:
        assert payload["pipeline_loaded"] is False, payload
        assert payload["status"] == "degraded", (
            f"no LLM key is configured, so /health must report degraded: "
            f"{payload!r}"
        )
        assert payload.get("reason"), (
            "degraded with no reason field — the reason is what tells an "
            "operator the key is missing rather than the container broken."
        )


def test_ready_is_readiness_and_follows_the_key(haystack_api, llm_key_configured):
    """`/ready` is 503 without a usable pipeline and 200 with one.

    The split from /health is the point: /health answers "is this process
    alive" for the orchestrator, /ready answers "can this serve a RAG query"
    for anything routing traffic. Asserting both directions here means the
    split cannot collapse back into one endpoint unnoticed.
    """
    status, body = haystack_api("/ready")
    if llm_key_configured:
        assert status == 200, (
            f"/ready returned {status} although the container has an LLM key. "
            f"The pipeline is not usable: {body[:600]}"
        )
        assert json.loads(body)["pipeline_loaded"] is True
    else:
        assert status == 503, (
            f"/ready returned {status}, not 503, with no LLM key configured. "
            f"Readiness must fail while the pipeline is unloaded, or nothing "
            f"downstream can tell a degraded container from a working one."
            f"\n{body[:600]}"
        )
        assert json.loads(body)["status"] == "degraded"


# ─── the proxy ──────────────────────────────────────────────────────
def test_proxy_root_advertises_the_pipeline(proxy):
    """`GET /` returns the pipeline id the wiki's ChatBot needs to open a session."""
    status, body = proxy("/")
    assert status == 200, f"chatbot-proxy GET / returned {status}: {body[:600]}"
    payload = json.loads(body)
    assert payload.get("pipeline_id"), (
        f"GET / carries no pipeline_id: {payload!r}. The ChatBot frontend reads "
        f"it here before calling /session."
    )


def test_session_creation_does_not_need_the_llm(proxy):
    """`POST /session` is 200 whether or not the pipeline is loaded.

    Session creation mints a uuid4 and returns it; nothing about it touches
    Haystack. Asserting it unconditionally is what distinguishes "the proxy is
    up and the LLM is not configured" — the CI state — from "the proxy is
    down", which would fail every chatbot assertion identically.
    """
    status, body = proxy("/session", method="POST", payload='{"pipeline_id": "hdp_pipeline"}')
    assert status == 200, f"POST /session returned {status}: {body[:600]}"
    payload = json.loads(body)
    assert payload.get("search_session_id"), (
        f"/session returned no search_session_id: {payload!r}"
    )


def test_chat_stream_without_a_key_fails_with_a_status_code(
    proxy_stream, llm_key_configured
):
    """`POST /chat-stream` answers 503, not 200 with an error frame.

    See this module's docstring: `EventSource.onmessage` has no branch for an
    error event, so a 200 was dropped on the floor and the chat UI hung
    silently. Only a non-200 reaches `onerror`. 502 is accepted as well as 503
    because the proxy distinguishes an upstream that answered badly (<500 from
    hayhooks, so 502) from one that could not serve at all (503); both are the
    honest shape, and pinning exactly one would make the test fail on a correct
    refinement.
    """
    if llm_key_configured:
        pytest.skip(
            "an LLM key is configured, so /chat-stream is expected to stream — "
            "see test_chat_stream_with_a_key_streams_an_answer, which is the "
            "assertion that applies to this stack"
        )
    # Read through the same EventSource-shaped probe as the success case, so
    # the two contracts are exercised by one client rather than two — and so
    # this cannot hang if the error path ever grows a keep-alive header of its
    # own.
    status, events = proxy_stream(
        "/chat-stream", json.dumps({"query": QUESTION}), budget=120
    )
    assert status in (502, 503), (
        f"/chat-stream returned HTTP {status} with no LLM key configured. A 200 "
        f"here is Wave 0 Issue 2: the frontend consumes this with EventSource, "
        f"whose onmessage handles only 'delta' and 'result', so a 200 carrying "
        f'{{"type":"error"}} is silently discarded and the chat UI hangs with '
        f"no message. onerror — i.e. a non-200 — is the only path that reaches "
        f"the user.\nFrames: {events}"
    )


def test_chat_stream_rejects_an_empty_query(proxy):
    """A missing query is a 400, and that holds with or without a key.

    Worth asserting alongside the 503 above: both are error paths, and a proxy
    that answered 503 to *everything* — including malformed input — would pass
    the Issue 2 assertion while telling the user nothing useful.
    """
    status, _ = proxy("/chat-stream", method="POST", payload="{}")
    assert status == 400, (
        f"/chat-stream returned {status} for a body with no query; 400 is the "
        f"documented answer."
    )


# ─── the end-to-end leg, only where a key exists ────────────────────
def test_chat_stream_with_a_key_streams_an_answer(proxy_stream, llm_key_configured):
    """Ask a question, get a streamed answer with source documents.

    This runs on a stack that has HDP_LLM_API_KEY — the validation box, never
    CI, which has no key and must not have one.

    The response is consumed frame by frame, stopping at the terminating
    `result` frame, because that is the only shape of client this endpoint
    has. An SSE response carries no Content-Length and is not chunked, so a
    `.read()` returns only when the connection closes; the Wave 4 box measured
    a server that had finished writing after 19 seconds against a reader still
    blocked ten minutes later. `EventSource` never notices — it acts on each
    frame as it arrives — which is why the chat UI worked throughout and only
    a non-browser client could see it. See `sse_in` in conftest.py, and the
    comment on the removed `Connection: keep-alive` header in
    docker/chatbot-proxy/server.py.
    """
    if not llm_key_configured:
        pytest.skip(
            "no HDP_LLM_API_KEY in the haystack container — the applicable "
            "assertion is test_chat_stream_without_a_key_fails_with_a_status_code, "
            "which runs instead"
        )
    status, events = proxy_stream("/chat-stream", json.dumps({"query": QUESTION}))
    assert status == 200, (
        f"/chat-stream returned HTTP {status} although the pipeline is loaded. "
        f"Frames received: {[e.get('type') for e in events]}"
    )
    assert events, "no SSE `data:` frames arrived before the deadline"

    deltas = [e for e in events if e.get("type") == "delta"]
    results = [e for e in events if e.get("type") == "result"]
    assert deltas, (
        f"the stream carried no delta frames, so nothing would render "
        f"progressively: {[e.get('type') for e in events]}"
    )
    assert len(results) == 1, (
        f"expected exactly one terminating result frame, got "
        f"{[e.get('type') for e in events]}"
    )

    answer = "".join(e.get("content", "") for e in deltas).strip()
    assert answer, "the deltas concatenated to an empty answer"

    result = results[0]["result"]
    documents = result.get("documents") or []
    assert documents, (
        f"the answer arrived with no source documents. Retrieval returned "
        f"nothing, which means the RAG pipeline answered from the model alone "
        f"— check that the hdp_wiki index is populated.\nanswer: {answer[:300]}"
    )
