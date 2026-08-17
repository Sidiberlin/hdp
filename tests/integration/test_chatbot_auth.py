"""Anonymous access to the chat route is rejected before the LLM pipeline.

Finding 9 (release QA 2026-08-17): the ChatBot REST handler opted out of
MediaWiki's read gate with `needsReadAccess() { return false; }`, so a
cookie-less request to /w/rest.php/bmbf/chat reached the metered LLM
pipeline — unmetered spend and a private-content oracle on any publicly
bound deployment.

Two layers hold that door shut now:

  1. MediaWiki core's BasicAccess gate. With the needsReadAccess() override
     gone from ChatBot\\Rest\\Chat, Module::executeHandler answers an
     anonymous request with 403 and a JSON rest-read-denied body before the
     handler — and therefore before ChatApi::request() and the chatbot
     proxy — ever runs. The HTTP test below pins that answer.
  2. An explicit authority guard inside execute() — isRegistered(), then
     isAllowed('read') — defence in depth against role configs that
     re-grant `*` read. It is not reachable through this route unless that
     happens, so the static test pins it instead.

Unmarked, so this runs in T3 as well as T4: it needs the wiki and the
mediawiki container, nothing else. There is deliberately no logged-in
positive chat test here — the T3 profile has no chatbot-proxy container,
so a logged-in request would hang in SSE after the headers commit (the
logged-in half of the verification runs on the box instead).
"""
import json


def test_anonymous_chat_request_is_a_403_json_error(anon):
    # Both params present so the same URL would genuinely have reached the
    # pipeline before the fix: sessionId is PARAM_REQUIRED and query feeds
    # ChatApi::request() verbatim.
    r = anon.fetch("/rest.php/bmbf/chat?query=test&sessionId=auth-pin")
    assert r.status == 403, (
        f"anonymous /bmbf/chat returned HTTP {r.status}, not 403 — the "
        f"request got past the framework gate (a 200 text/event-stream "
        f"means the guard fired too late, an HTML page means an error page "
        f"stood in for the gate).\nFirst 300 bytes: {r.text[:300]!r}"
    )
    body = json.loads(r.text)  # an HTML error page must raise — that is the point
    assert body.get("error") == "rest-read-denied", (
        f"unexpected error body for anonymous /bmbf/chat: {body!r} — "
        f"expected the canonical MediaWiki rest-read-denied JSON shape."
    )


def test_chat_handler_no_longer_opts_out_of_read_access(mw_exec):
    proc = mw_exec("cat", "/var/www/html/w/extensions/ChatBot/src/Rest/Chat.php")
    assert proc.returncode == 0, (
        "/var/www/html/w/extensions/ChatBot/src/Rest/Chat.php could not be "
        f"read inside the mediawiki container (exit {proc.returncode}).\n"
        f"{proc.stderr[-800:]}"
    )
    assert "needsReadAccess" not in proc.stdout, (
        "Chat.php still overrides needsReadAccess() — the BasicAccess "
        "opt-out is back, and layer 1 of the anonymous lockout is gone."
    )
