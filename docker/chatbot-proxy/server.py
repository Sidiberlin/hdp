#!/usr/bin/env python3
"""
ChatBot Proxy: translates between BlueSpice ChatBot (Deepset API format)
and Haystack hayhooks RAG pipeline.

Endpoints:
  GET  /             → pipeline info for session creation
  POST /session      → create a chat session
  POST /chat-stream  → SSE stream of RAG answer

The ChatBot extension's Connector.php does:
  - GET  {apiUrl}           → expects {"pipeline_id": "..."}
  - POST {sessionApiUrl}    → with {"pipeline_id": "..."} → expects {"search_session_id": "..."}
  - POST {apiUrl}/chat-stream → with Deepset body → expects SSE stream
"""

import json
import logging
import os
import urllib.error
import urllib.request
import uuid
from http.server import BaseHTTPRequestHandler, HTTPServer
from urllib.parse import urlparse

# ─── Config ──────────────────────────────────────────────────────────────
HAYHOOKS_URL = os.environ.get("HAYHOOKS_URL", "http://haystack:1417")
PIPELINE_NAME = os.environ.get("PIPELINE_NAME", "hdp_pipeline")
PROXY_PORT = int(os.environ.get("CHATBOT_PROXY_PORT", "8080"))

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
log = logging.getLogger("chatbot-proxy")


def call_hayhooks(question: str) -> dict:
    """Call the Haystack pipeline and return the raw response."""
    url = f"{HAYHOOKS_URL}/{PIPELINE_NAME}/run"
    body = json.dumps({
        "question": question,
        "query": question,
        "path": "rag",
    }).encode()
    req = urllib.request.Request(
        url,
        data=body,
        headers={"Content-Type": "application/json"},
        method="POST",
    )
    log.info(f"Calling hayhooks: {url} question='{question[:80]}'")
    try:
        with urllib.request.urlopen(req, timeout=120) as resp:
            return json.loads(resp.read().decode())
    except urllib.error.HTTPError as e:
        error_body = e.read().decode() if e.fp else str(e)
        log.error(f"Hayhooks HTTP {e.code}: {error_body[:200]}")
        return {"error": error_body, "status": e.code}
    except Exception as e:
        log.error(f"Hayhooks call failed: {e}")
        return {"error": str(e)}


def build_result_from_haystack(hay_response: dict, query: str) -> dict:
    """
    Convert Haystack pipeline response into the Deepset-compatible format
    the ChatBot frontend expects.

    Haystack returns:
      {"answers": [{"answer": "...", ...}], "documents": [...], ...}
    or possibly wrapped in a "result" key depending on hayhooks version.

    Deepset/frontend expects:
      {
        "query": "...",
        "answers": [
          {"answer": query, ...},              # [0] reformulated question
          {"answer": "actual answer", "result_id": "...", "meta": {...}}
        ],
        "documents": [
          {"id": "...", "meta": {"prefixed_title": "...", "uri": "..."}}
        ]
      }
    """
    # Unwrap hayhooks response if needed
    data = hay_response.get("result", hay_response)

    # The pipeline returns answers under answer_joiner.answers, not top-level
    answer_joiner = data.get("answer_joiner", {})
    answers = answer_joiner.get("answers", []) if answer_joiner else data.get("answers", [])

    # Use the LAST answer (index 0 is the reformulated query, index 1 is the real answer)
    answer_text = ""
    if len(answers) >= 2:
        last = answers[-1] if isinstance(answers[-1], dict) else {}
        answer_text = last.get("answer", "") or last.get("data", "") or str(last)
    elif answers:
        first = answers[0] if isinstance(answers[0], dict) else {}
        answer_text = first.get("answer", "") or first.get("reply", "") or str(first)
    if not answer_text:
        answer_text = "Es konnte keine Antwort gefunden werden."

    # Extract documents from the answer object (which carries the retrieved docs)
    docs = []
    if answers and isinstance(answers[-1], dict):
        docs = answers[-1].get("documents", []) or []
    if not docs:
        docs = data.get("documents", data.get("retrieved_documents", []))
    deepset_docs = []
    for i, doc in enumerate(docs[:10]):
        meta = doc.get("meta", doc) if isinstance(doc, dict) else {}
        content = doc.get("content", "") if isinstance(doc, dict) else str(doc)
        deepset_docs.append({
            "id": doc.get("id", str(i)) if isinstance(doc, dict) else str(i),
            "content": content[:200] if content else "",
            "meta": {
                "prefixed_title": (
                    meta.get("prefixed_title")
                    or meta.get("display_title")
                    or meta.get("title", "Unbekannt")
                ),
                "uri": meta.get("uri", ""),
                "namespace": meta.get("namespace", 0),
                "page_id": meta.get("page_id", 0),
                "title_level_1": meta.get("title_level_1", ""),
            },
        })

    result_id = str(uuid.uuid4())
    query_id = str(uuid.uuid4())

    return {
        "query": query,
        "answers": [
            {"answer": query},  # [0] = reformulated question
            {
                "answer": answer_text,
                "result_id": result_id,
                "query_id": query_id,
                "meta": {
                    "doc_ids": [d["id"] for d in deepset_docs],
                    "documents": deepset_docs,
                },
            },
        ],
        "documents": deepset_docs,
    }


class ProxyHandler(BaseHTTPRequestHandler):
    def log_message(self, fmt, *args):
        log.info(f"{self.client_address[0]} {fmt % args}")

    def _send_json(self, code: int, data: dict):
        body = json.dumps(data).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _read_body(self) -> dict:
        length = int(self.headers.get("Content-Length", 0))
        if length == 0:
            return {}
        raw = self.rfile.read(length)
        try:
            return json.loads(raw)
        except json.JSONDecodeError:
            return {}

    # GET / → pipeline info (for session creation)
    def do_GET(self):
        if self.path == "/" or self.path == "":
            self._send_json(200, {"pipeline_id": PIPELINE_NAME})
        else:
            self._send_json(404, {"error": "Not found"})

    # POST /session → create session
    # POST /chat-stream → SSE stream of RAG answer
    def do_POST(self):
        path = urlparse(self.path).path
        body = self._read_body()

        if path == "/session":
            session_id = str(uuid.uuid4())
            self._send_json(200, {
                "search_session_id": session_id,
                "pipeline_id": body.get("pipeline_id", PIPELINE_NAME),
            })
            return

        if path == "/chat-stream":
            self._handle_chat_stream(body)
            return

        self._send_json(404, {"error": f"Unknown endpoint: {path}"})

    def _handle_chat_stream(self, body: dict):
        query = body.get("query", "")
        if not query:
            self._send_json(400, {"error": "Missing query"})
            return

        # Call Haystack
        hay_response = call_hayhooks(query)

        if "error" in hay_response:
            error_msg = hay_response["error"][:500]
            # Upstream failed before a single byte of the stream was written,
            # so this is still an ordinary HTTP response and must carry a real
            # failure status. See _send_sse_error for why 200 was wrong.
            upstream = hay_response.get("status")
            status = 502 if isinstance(upstream, int) and upstream < 500 else 503
            self._send_sse_error(error_msg, status=status)
            return

        # Build result
        result = build_result_from_haystack(hay_response, query)
        answer_text = result["answers"][1]["answer"]

        # Stream as SSE: send answer in chunks as "delta" events,
        # then send the final "result" event.
        self.send_response(200)
        self.send_header("Content-Type", "text/event-stream")
        self.send_header("Cache-Control", "no-cache")
        self.send_header("Connection", "keep-alive")
        self.send_header("X-Accel-Buffering", "no")
        self.end_headers()

        # Send deltas (split answer into word chunks for streaming effect)
        words = answer_text.split(" ")
        chunk_size = 5  # words per delta
        for i in range(0, len(words), chunk_size):
            chunk = " ".join(words[i:i + chunk_size])
            if i + chunk_size < len(words):
                chunk += " "
            delta_event = {"type": "delta", "content": chunk}
            self.wfile.write(f"data: {json.dumps(delta_event)}\n\n".encode())
            self.wfile.flush()

        # Send final result event
        result_event = {
            "type": "result",
            "result": result,
            "query_id": result["answers"][1].get("query_id", str(uuid.uuid4())),
        }
        self.wfile.write(f"data: {json.dumps(result_event)}\n\n".encode())
        self.wfile.flush()

        log.info(f"Chat stream complete: query='{query[:60]}' answer_len={len(answer_text)}")

    def _send_sse_error(self, message: str, status: int = 503):
        """Report a stream that never started, with an honest status code.

        This used to send 200. That was wrong twice over.

        The frontend consumes this endpoint with `EventSource` (see
        DeepsetApi in ChatBot's bmbf.chat.bundle.js). Its `onmessage` handler
        branches on `type === 'delta'` and `type === 'result'` and has no
        branch for `type === 'error'` — so a 200 carrying an error event was
        silently discarded, the surrounding promise neither resolved nor
        rejected, and the chat UI hung with no message at all. The only path
        that surfaces a failure to the user is `EventSource.onerror`, and that
        fires on a non-200 response. So the status code is not cosmetic here;
        it is the entire user-visible error path.

        It also made the failure invisible to CI, which reasonably asserts a
        clean 503 when no LLM key is configured.

        The SSE error frame is still written as the body. EventSource will not
        deliver it (a non-200 fails the connection per spec, which is what we
        want), but any plain HTTP client — curl, a test, the proxy's own smoke
        checks — still gets a readable reason instead of an empty body.
        """
        self.send_response(status)
        self.send_header("Content-Type", "text/event-stream")
        self.send_header("Cache-Control", "no-cache")
        self.end_headers()
        error_event = {"type": "error", "message": message}
        self.wfile.write(f"data: {json.dumps(error_event)}\n\n".encode())
        self.wfile.flush()


if __name__ == "__main__":
    server = HTTPServer(("0.0.0.0", PROXY_PORT), ProxyHandler)
    log.info(f"ChatBot proxy listening on :{PROXY_PORT}")
    log.info(f"Hayhooks backend: {HAYHOOKS_URL}")
    server.serve_forever()
