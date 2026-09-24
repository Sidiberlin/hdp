# SPDX-License-Identifier: GPL-3.0-only
# Copyright (C) 2026 Sidiberlin
"""T6 — FastAPI TestClient against ingest_api.router, with the wiki,
OpenSearch and the D4 lock monkeypatched.

Container tier only (F20): ingest_api.py imports ingest_hdp_wiki, which
imports haystack/opensearch-haystack/pymysql/requests — none of which
tests/unit's tier installs. This file is deliberately NOT under tests/unit
or tests/haystack; scripts/ci/pytest.sh's `--tier api` runs it inside the
haystack image (already ships all of the above, plus pytest), with this
directory bind-mounted, never baked into the image.

No live stack: every call that would touch MariaDB, MediaWiki or OpenSearch
is monkeypatched at the seam ingest_api.py exposes for exactly this
(`_db_connect`, `_login`, `_edit_page`, `_ensure_category_page`, `_embedder`,
`_store`, and `ingest_hdp_wiki.get_pages_by_title`/`get_staged_revisions`/
`process_page`/`acquire_lock`).
"""
import ingest_api
import pytest
from fastapi import FastAPI
from fastapi.testclient import TestClient


class _FakeConn:
    """Stands in for the pymysql connection _do_ingest opens/closes."""

    def close(self):
        pass


class _FakeLockFile:
    def __init__(self):
        self.closed = False

    def close(self):
        self.closed = True


def _page_row(title, page_id=1, page_latest=100):
    return {
        "page_id": page_id,
        "page_namespace": 0,
        "page_title": title,
        "page_is_redirect": 0,
        "page_latest": page_latest,
        "page_content_model": "wikitext",
    }


@pytest.fixture(autouse=True)
def _isolated_config(monkeypatch):
    """A known API key, no category allow-list, and a fresh rate limiter for
    every test — tests must not see each other's rate-limit state."""
    monkeypatch.setattr(ingest_api, "API_KEY", "test-key-123")
    monkeypatch.setenv("HDP_INGEST_ALLOWED_CATEGORIES", "")
    monkeypatch.setattr(ingest_api, "_rate_limiter", ingest_api.RateLimiter(6))
    yield


@pytest.fixture(autouse=True)
def _no_real_wiki_or_search(monkeypatch):
    """Default happy-path stand-ins: every page's edit succeeds
    (non-nochange), lands in the DB with a fresh page_id, and "indexing"
    just returns a document count — no MediaWiki, OpenSearch, or embedder
    is ever touched unless a test explicitly overrides one of these."""
    monkeypatch.setattr(ingest_api, "_db_connect", lambda: _FakeConn())
    monkeypatch.setattr(ingest_api, "_login", lambda: object())
    monkeypatch.setattr(ingest_api, "_ensure_category_page", lambda *a, **k: None)
    monkeypatch.setattr(ingest_api, "_embedder", lambda: object())
    monkeypatch.setattr(ingest_api, "_store", lambda: object())

    monkeypatch.setattr(ingest_api, "_edit_page", lambda session, title, content: ("Success", 1))
    monkeypatch.setattr(
        ingest_api.ingest, "get_pages_by_title",
        lambda conn, titles: [_page_row(t) for t in titles],
    )
    monkeypatch.setattr(ingest_api.ingest, "get_staged_revisions", lambda: {})
    monkeypatch.setattr(ingest_api.ingest, "process_page", lambda *a, **k: 1)
    monkeypatch.setattr(ingest_api.ingest, "acquire_lock", lambda: _FakeLockFile())
    yield


@pytest.fixture
def client():
    app = FastAPI()
    app.include_router(ingest_api.router)
    return TestClient(app)


def _payload(pages=None, category="Betriebshandbuch"):
    # `pages if pages is not None else [...]`, deliberately NOT `pages or
    # [...]` — an empty list is a legitimate, falsy value here (see
    # test_empty_pages_list_is_422), and `or` would silently replace it
    # with the default page instead of sending the empty list under test.
    if pages is None:
        pages = [{"title": "Backup-Konzept", "content": "Der Backup-Prozess..."}]
    return {"category": category, "pages": pages}


AUTH = {"Authorization": "Bearer test-key-123"}


# ─── D8 auth semantics ──────────────────────────────────────────────

def test_no_key_configured_is_503(client, monkeypatch):
    monkeypatch.setattr(ingest_api, "API_KEY", "")
    r = client.post("/v1/ingest/pages", json=_payload(), headers=AUTH)
    assert r.status_code == 503
    body = r.json()["detail"]
    assert body["error"]["type"] == "service_unavailable"
    assert body["error"]["code"] == "ingestion_api_disabled"


def test_missing_authorization_header_is_401(client):
    r = client.post("/v1/ingest/pages", json=_payload())
    assert r.status_code == 401
    assert r.json()["detail"]["error"]["code"] == "invalid_api_key"


def test_malformed_authorization_header_is_401(client):
    r = client.post("/v1/ingest/pages", json=_payload(), headers={"Authorization": "test-key-123"})
    assert r.status_code == 401
    assert r.json()["detail"]["error"]["code"] == "invalid_api_key"


def test_wrong_key_is_401(client):
    r = client.post("/v1/ingest/pages", json=_payload(), headers={"Authorization": "Bearer wrong"})
    assert r.status_code == 401
    assert r.json()["detail"]["error"]["type"] == "invalid_request_error"


def test_disallowed_category_is_403(client, monkeypatch):
    monkeypatch.setenv("HDP_INGEST_ALLOWED_CATEGORIES", "Allowed-One,Allowed-Two")
    r = client.post("/v1/ingest/pages", json=_payload(category="Something-Else"), headers=AUTH)
    assert r.status_code == 403
    body = r.json()["detail"]
    assert body["error"]["code"] == "category_not_allowed"
    assert body["error"]["param"] == "category"


def test_allowed_category_passes_when_in_the_list(client, monkeypatch):
    monkeypatch.setenv("HDP_INGEST_ALLOWED_CATEGORIES", "Betriebshandbuch")
    r = client.post("/v1/ingest/pages", json=_payload(category="Betriebshandbuch"), headers=AUTH)
    assert r.status_code == 200


# ─── 422: body limits enforced before any wiki write (D12) ─────────────

def test_oversized_content_is_422(client):
    payload = _payload(pages=[{"title": "Big", "content": "x" * (512 * 1024 + 1)}])
    r = client.post("/v1/ingest/pages", json=payload, headers=AUTH)
    assert r.status_code == 422


def test_too_many_pages_is_422(client):
    pages = [{"title": f"Page{i}", "content": "x"} for i in range(51)]  # default cap is 50
    r = client.post("/v1/ingest/pages", json=_payload(pages=pages), headers=AUTH)
    assert r.status_code == 422


def test_empty_pages_list_is_422(client):
    r = client.post("/v1/ingest/pages", json=_payload(pages=[]), headers=AUTH)
    assert r.status_code == 422


def test_bad_title_is_422(client):
    r = client.post(
        "/v1/ingest/pages",
        json=_payload(pages=[{"title": "MediaWiki:Sidebar", "content": "x"}]),
        headers=AUTH,
    )
    assert r.status_code == 422


# ─── 429: rate limit and lock-held ──────────────────────────────────────

def test_rate_limit_exceeded_is_429(client, monkeypatch):
    monkeypatch.setattr(ingest_api, "_rate_limiter", ingest_api.RateLimiter(1))
    first = client.post("/v1/ingest/pages", json=_payload(), headers=AUTH)
    assert first.status_code == 200
    second = client.post("/v1/ingest/pages", json=_payload(), headers=AUTH)
    assert second.status_code == 429
    body = second.json()["detail"]
    assert body["error"]["code"] == "rate_limit_exceeded"
    assert "Retry-After" in second.headers


def test_lock_held_is_429(client, monkeypatch):
    def _raise():
        raise ingest_api.ingest.IngestLockHeld("/var/lib/hdp-ingest/ingest.lock")

    monkeypatch.setattr(ingest_api.ingest, "acquire_lock", _raise)
    r = client.post("/v1/ingest/pages", json=_payload(), headers=AUTH)
    assert r.status_code == 429
    body = r.json()["detail"]
    assert body["error"]["code"] == "ingestion_in_progress"
    assert "Retry-After" in r.headers


# ─── 200: the exact response shape ──────────────────────────────────────

def test_successful_ingest_response_shape(client):
    r = client.post("/v1/ingest/pages", json=_payload(), headers=AUTH)
    assert r.status_code == 200
    body = r.json()
    assert set(body.keys()) == {"indexed", "skipped", "failed", "page_urls", "errors"}
    assert body["indexed"] == 1
    assert body["skipped"] == 0
    assert body["failed"] == 0
    assert body["errors"] == []
    assert len(body["page_urls"]) == 1
    assert "Backup-Konzept" in body["page_urls"][0]


def test_lock_is_released_after_the_request(client, monkeypatch):
    holder = {}

    def _fake_acquire():
        lf = _FakeLockFile()
        holder["lock"] = lf
        return lf

    monkeypatch.setattr(ingest_api.ingest, "acquire_lock", _fake_acquire)
    r = client.post("/v1/ingest/pages", json=_payload(), headers=AUTH)
    assert r.status_code == 200
    assert holder["lock"].closed is True


def test_nochange_and_already_staged_is_skipped(client, monkeypatch):
    """D12: skipped means the edit was a no-op AND the index already holds
    that revision."""
    monkeypatch.setattr(ingest_api, "_edit_page", lambda session, title, content: ("nochange", 1))
    monkeypatch.setattr(ingest_api.ingest, "get_staged_revisions", lambda: {"1": "100"})
    monkeypatch.setattr(
        ingest_api.ingest, "get_pages_by_title",
        lambda conn, titles: [_page_row(t, page_id=1, page_latest=100) for t in titles],
    )
    r = client.post("/v1/ingest/pages", json=_payload(), headers=AUTH)
    assert r.status_code == 200
    body = r.json()
    assert body["skipped"] == 1
    assert body["indexed"] == 0
    assert body["failed"] == 0


def test_nochange_but_never_indexed_still_indexes(client, monkeypatch):
    """A page whose wiki content is a no-op edit but has never been staged
    in the index must still be indexed — 'skipped' must never lie about
    what is searchable."""
    monkeypatch.setattr(ingest_api, "_edit_page", lambda session, title, content: ("nochange", 1))
    monkeypatch.setattr(ingest_api.ingest, "get_staged_revisions", lambda: {})
    r = client.post("/v1/ingest/pages", json=_payload(), headers=AUTH)
    assert r.status_code == 200
    body = r.json()
    assert body["indexed"] == 1
    assert body["skipped"] == 0


# ─── partial and total failure (D12) ────────────────────────────────────

def test_partial_failure_is_200_with_failed_greater_than_zero(client, monkeypatch):
    def _edit(session, title, content):
        if title == "Bad-Page":
            raise RuntimeError("permission denied")
        return ("Success", 1)

    monkeypatch.setattr(ingest_api, "_edit_page", _edit)
    payload = _payload(pages=[
        {"title": "Good-Page", "content": "ok"},
        {"title": "Bad-Page", "content": "ok"},
    ])
    r = client.post("/v1/ingest/pages", json=payload, headers=AUTH)
    assert r.status_code == 200
    body = r.json()
    assert body["indexed"] == 1
    assert body["failed"] == 1
    assert len(body["errors"]) == 1
    assert body["errors"][0]["title"] == "Bad-Page"
    assert body["errors"][0]["code"] == "edit_failed"


def test_every_page_failing_is_502(client, monkeypatch):
    def _edit(session, title, content):
        raise RuntimeError("wiki is down")

    monkeypatch.setattr(ingest_api, "_edit_page", _edit)
    r = client.post("/v1/ingest/pages", json=_payload(), headers=AUTH)
    assert r.status_code == 502
    body = r.json()["detail"]
    assert body["error"]["code"] == "ingestion_failed"


def test_index_failure_is_recorded_per_page(client, monkeypatch):
    def _process_page(session, embedder, store, page, dry_run=False):
        raise RuntimeError("opensearch write failed")

    monkeypatch.setattr(ingest_api.ingest, "process_page", _process_page)
    r = client.post("/v1/ingest/pages", json=_payload(), headers=AUTH)
    assert r.status_code == 502  # the only page failed -> every page failed
    body = r.json()["detail"]
    assert body["error"]["code"] == "ingestion_failed"
