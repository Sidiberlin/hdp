# SPDX-License-Identifier: GPL-3.0-only
# Copyright (C) 2026 Sidiberlin
"""HDP wiki ingestion API — POST /v1/ingest/pages.

Lets an external tool (an agent, an importer, a nightly job on another box)
write content into the wiki and have it become answerable by the chatbot in
one call, authenticated by a bearer key nothing in this stack had before
(F6 — hdp_api_server.py has no auth of any kind, and chatbot-proxy posts to
it with no bearer either; there was no existing pattern to copy).

Split into two modules on purpose (D7, the same Wave-2 pattern as
wikitext.py/ingest_select.py):

  - Pure, stdlib-only: docker/haystack/ingest_validate.py — validate_title,
    validate_category, ensure_category, error_envelope, the rate-limiter.
    Imported directly by tests/unit/test_ingest_api.py, with no FastAPI, no
    pydantic, no requests, no pymysql, no haystack — tests/unit's tier
    installs nothing but pytest.
  - Impure, here: `router`, the pydantic request/response models, the
    bearer dependency, the MediaWiki edit/login calls, the lazy embedder/
    store singletons, and the ingest step itself, which reuses
    ingest_hdp_wiki.get_pages_by_title()/process_page() unchanged — this
    module does not reimplement rendering, splitting, or writing.

hdp_api_server.py mounts `router` with two lines; nothing else in that file
changes, and the auth dependency below applies to these routes only — a
global one would also gate the existing, unauthenticated /hdp_pipeline/run
that chatbot-proxy depends on (F6).

Auth semantics (D8), OpenAI's error envelope shape:

    {"error": {"message": ..., "type": ..., "param": ..., "code": ...}}

  HDP_INGEST_API_KEY unset/empty         -> 503 service_unavailable / ingestion_api_disabled
  no/malformed Authorization header      -> 401 invalid_request_error / invalid_api_key
  wrong key                              -> 401 invalid_request_error / invalid_api_key
  category not in HDP_INGEST_ALLOWED_CATEGORIES -> 403 invalid_request_error / category_not_allowed
  over the per-key rate limit            -> 429 rate_limit_error / rate_limit_exceeded (+Retry-After)
  an ingestion already holds the D4 lock -> 429 rate_limit_error / ingestion_in_progress (+Retry-After)

Fail closed: no key configured means the route is off, checked before the
Authorization header is even read — never "open because unconfigured".
Comparison is hmac.compare_digest, not ==, to avoid a timing side-channel.

In-process, synchronous ingestion (D11): the route writes the wiki pages,
then embeds and indexes them in this process, serialized by the same D4
lock every other ingestion entry point takes — never a subprocess, so the
response's indexed/skipped/failed counts are real return values, not
scraped from log lines. The embedder is built lazily on first use and
cached at module scope: a page cap, one ingestion at a time (the lock), and
HDP_HAYSTACK_MEM_LIMIT=4g guidance (see README-DOCKER.md) are what keep a
second copy of the local embedding model from OOM-killing the same
container that serves RAG queries.
"""
import hmac
import logging
import os
import threading

# Reuses everything without reimplementing it: DB/wiki/OpenSearch config,
# mw_api_login (D9's user/password args), get_pages_by_title (D12),
# process_page, make_embedder, the OpenSearchDocumentStore/DuplicatePolicy
# imports, and acquire_lock/IngestLockHeld (D4) — one lock, shared by every
# entry point regardless of which container or process it runs in.
import ingest_hdp_wiki as ingest
import pymysql
import requests
from fastapi import APIRouter, Depends, Header, HTTPException
from ingest_select import classify_pages
from ingest_validate import (
    RateLimiter,
    ensure_category,
    error_envelope,
    validate_category,
    validate_title,
)
from pydantic import BaseModel, Field, model_validator

log = logging.getLogger("hdp-ingest-api")

# ─── Configuration ──────────────────────────────────────────────────
API_KEY = os.environ.get("HDP_INGEST_API_KEY", "")
BOT_USER = os.environ.get("HDP_INGEST_BOT_USER", "HDPIngestBot")
BOT_PASSWORD = os.environ.get("HDP_INGEST_BOT_PASSWORD", "")
MW_SERVER = os.environ.get("MW_SERVER", "http://localhost:8080")

MAX_PAGES_PER_REQUEST = int(os.environ.get("HDP_INGEST_API_MAX_PAGES", "50"))
MAX_CONTENT_BYTES = 512 * 1024
MAX_REQUEST_BYTES = 8 * 1024 * 1024
RATE_LIMIT_PER_MIN = int(os.environ.get("HDP_INGEST_API_RATE_PER_MIN", "6"))


def _allowed_categories() -> set:
    """HDP_INGEST_ALLOWED_CATEGORIES, comma-separated. Empty (the default)
    means any category — 403 category_not_allowed only means something once
    an operator has actually opted into a list."""
    raw = os.environ.get("HDP_INGEST_ALLOWED_CATEGORIES", "")
    return {c.strip() for c in raw.split(",") if c.strip()}


_rate_limiter = RateLimiter(RATE_LIMIT_PER_MIN)


class PageIn(BaseModel):
    title: str
    content: str = ""

    @model_validator(mode="after")
    def _validate(self):
        err = validate_title(self.title)
        if err:
            raise ValueError(f"pages[].title: {err}")
        if len(self.content.encode("utf-8")) > MAX_CONTENT_BYTES:
            raise ValueError(f"pages[].content must not exceed {MAX_CONTENT_BYTES} bytes")
        return self


class IngestRequest(BaseModel):
    category: str
    pages: list = Field(min_length=1, max_length=MAX_PAGES_PER_REQUEST)

    @model_validator(mode="after")
    def _validate(self):
        err = validate_category(self.category)
        if err:
            raise ValueError(f"category: {err}")
        # Re-validate as PageIn: pydantic's `list` annotation above (rather
        # than `list[PageIn]`) is deliberate — max_length on a typed-item
        # list still constrains list length in pydantic v2, but coercing the
        # error messages for a *specific* page (pages[N].title: ...) reads
        # better hand-rolled than through pydantic's nested-model path. See
        # PageIn._validate for the per-page rules actually enforced.
        self.pages = [p if isinstance(p, PageIn) else PageIn(**p) for p in self.pages]
        total = sum(
            len(p.content.encode("utf-8")) + len(p.title.encode("utf-8"))
            for p in self.pages
        )
        if total > MAX_REQUEST_BYTES:
            raise ValueError(f"request body must not exceed {MAX_REQUEST_BYTES} bytes of page content")
        return self


class PageError(BaseModel):
    title: str
    message: str
    code: str


class IngestResponse(BaseModel):
    indexed: int
    skipped: int
    failed: int
    page_urls: list
    errors: list


# ─── Impure half ────────────────────────────────────────────────────

router = APIRouter()

_embedder_cache = None
_embedder_lock = threading.Lock()
_store_cache = None
_store_lock = threading.Lock()


def require_api_key(authorization: str = Header(default=None)):
    """The auth dependency (D7/D8) — scoped to this router only, so the
    existing, unauthenticated /hdp_pipeline/run and /health/ /ready routes
    are unaffected (F6)."""
    if not API_KEY:
        raise HTTPException(
            status_code=503,
            detail=error_envelope(
                "The ingestion API is not configured — set HDP_INGEST_API_KEY.",
                "service_unavailable", "ingestion_api_disabled",
            ),
        )
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(
            status_code=401,
            detail=error_envelope(
                "Missing or malformed Authorization header — expected 'Bearer <key>'.",
                "invalid_request_error", "invalid_api_key",
            ),
        )
    token = authorization[len("Bearer "):]
    if not hmac.compare_digest(token, API_KEY):
        raise HTTPException(
            status_code=401,
            detail=error_envelope("Incorrect API key.", "invalid_request_error", "invalid_api_key"),
        )


def _db_connect():
    # autocommit=True — found live on the QA box: unlike ingest_hdp_wiki.py's
    # main(), which does one read before any writes, this connection
    # interleaves reads (get_pages_by_title) with writes that land through a
    # *different* connection (the MediaWiki web server's own, via the HTTP
    # edit call). Under pymysql's default autocommit=False and MariaDB's
    # REPEATABLE READ, the first SELECT on this connection opens a
    # transaction and pins its snapshot — every later SELECT in the same
    # request then sees that snapshot, not the row the edit just committed,
    # and get_pages_by_title() reports "page not found after edit" for every
    # page but the first. autocommit=True starts a fresh transaction (and
    # snapshot) per statement, which is what a read-only connection that
    # must see other connections' latest commits needs.
    return pymysql.connect(
        host=ingest.DB_HOST, port=ingest.DB_PORT, user=ingest.DB_USER,
        password=ingest.DB_PASS, database=ingest.DB_NAME, charset="utf8mb4",
        autocommit=True,
    )


def _login() -> requests.Session:
    """D9: the bot account when HDP_INGEST_BOT_PASSWORD is set, else a
    WARNING-logged fallback to the admin account — the fallback is what
    keeps this a non-breaking change for an install that has not re-run
    setup.sh since upgrading."""
    if BOT_PASSWORD:
        return ingest.mw_api_login(BOT_USER, BOT_PASSWORD)
    log.warning(
        f"HDP_INGEST_BOT_PASSWORD not set — the ingestion API is writing as "
        f"{ingest.MW_ADMIN_USER} (admin), not a dedicated bot account. Run "
        f"setup.sh to create {BOT_USER} (D9)."
    )
    return ingest.mw_api_login()


def _embedder():
    """Lazy, module-cached (D11) — never built until the first request that
    actually needs to index a page, and never rebuilt after that."""
    global _embedder_cache
    with _embedder_lock:
        if _embedder_cache is None:
            log.info(f"building the ingestion API's embedder (provider={ingest.EMBEDDING_PROVIDER}) — first use only")
            _embedder_cache = ingest.make_embedder(ingest.EMBEDDING_PROVIDER)
        return _embedder_cache


def _store():
    global _store_cache
    with _store_lock:
        if _store_cache is None:
            _store_cache = ingest.OpenSearchDocumentStore(
                hosts=[f"{ingest.OPENSEARCH_HOST}:{ingest.OPENSEARCH_PORT}"],
                use_ssl=True,
                verify_certs=False,
                http_auth=["admin", ingest.OPENSEARCH_PASSWORD],
                index=ingest.INDEX_NAME,
                embedding_dim=ingest.EMBEDDING_DIM,
                similarity="cosine",
            )
        return _store_cache


def _page_url(title: str) -> str:
    return f"{MW_SERVER.rstrip('/')}/w/index.php/{title.replace(' ', '_')}"


def _csrf_token(session: requests.Session) -> str:
    r = session.get(ingest.MW_API_URL, params={
        "action": "query", "meta": "tokens", "format": "json",
    }, timeout=30)
    return r.json().get("query", {}).get("tokens", {}).get("csrftoken", "")


def _edit_page(session: requests.Session, title: str, content: str):
    """POST action=edit with bot=1 (D9 attribution). Returns (outcome,
    page_id): outcome is "Success" or "nochange" — both are a successful
    edit in MediaWiki's own terms, "nochange" just means the submitted
    content equals what is already there. Raises on anything else (a real
    edit error — permissions, a malformed title); the caller records that
    as a per-page failure rather than failing the whole request (D12).
    """
    token = _csrf_token(session)
    r = session.post(ingest.MW_API_URL, data={
        "action": "edit", "format": "json",
        "title": title, "text": content,
        "bot": "1",
        "summary": "Ingestion API: content update",
        "token": token,
    }, timeout=60)
    data = r.json()
    if "error" in data:
        raise RuntimeError(data["error"].get("info", str(data["error"])))
    edit = data.get("edit", {})
    if "nochange" in edit:
        return "nochange", edit.get("pageid")
    if edit.get("result") == "Success":
        return "Success", edit.get("pageid")
    raise RuntimeError(f"unexpected edit response: {data}")


def _ensure_category_page(session: requests.Session, category: str) -> None:
    """Create Category:<category> if it does not exist yet (D12), so the
    category browses cleanly. Category pages are namespace 14 — never
    indexed (INDEXABLE_NAMESPACES). Best-effort: a failure here must not
    fail the request, since the content pages are correctly tagged either
    way and Special:WantedCategories would simply list it.
    """
    try:
        title = f"Category:{category}"
        r = session.get(ingest.MW_API_URL, params={
            "action": "query", "titles": title, "format": "json",
        }, timeout=30)
        pages = r.json().get("query", {}).get("pages", {})
        if any("missing" not in p for p in pages.values()):
            return
        token = _csrf_token(session)
        session.post(ingest.MW_API_URL, data={
            "action": "edit", "format": "json",
            "title": title,
            "text": f"Automatically created by the ingestion API for the {category} category.",
            "bot": "1",
            "summary": "Ingestion API: create category page",
            "token": token,
        }, timeout=60)
    except Exception as e:
        log.warning(f"could not ensure Category:{category} exists: {e}")


def _do_ingest(payload: IngestRequest) -> IngestResponse:
    conn = _db_connect()
    try:
        session = _login()
        _ensure_category_page(session, payload.category)
        staged_revs = ingest.get_staged_revisions()

        indexed = failed = skipped = 0
        page_urls = []
        errors = []
        to_process = []  # [(PageIn, page_row), ...] — pages that need (re)indexing

        for page_in in payload.pages:
            content = ensure_category(page_in.content, payload.category)
            db_title = page_in.title.replace(" ", "_")
            try:
                outcome, _page_id = _edit_page(session, page_in.title, content)
            except Exception as e:
                failed += 1
                errors.append(PageError(title=page_in.title, message=str(e), code="edit_failed"))
                continue

            rows = ingest.get_pages_by_title(conn, [db_title])
            if not rows:
                failed += 1
                errors.append(PageError(
                    title=page_in.title, message="page not found after edit",
                    code="not_found_after_edit",
                ))
                continue
            row = rows[0]
            page_urls.append(_page_url(page_in.title))

            if outcome == "nochange":
                # D12: skipped means the edit was a nochange AND the index
                # already holds that revision — reuse classify_pages (the
                # same tested new/edited/unchanged policy --missing-only
                # uses) rather than re-deriving the comparison here.
                _, counts = classify_pages([row], staged_revs)
                if counts["unchanged"] == 1:
                    skipped += 1
                    continue

            to_process.append((page_in, row))

        if to_process:
            embedder = _embedder()
            store = _store()
            for page_in, row in to_process:
                try:
                    ingest.process_page(session, embedder, store, row, dry_run=False)
                    indexed += 1
                except Exception as e:
                    failed += 1
                    errors.append(PageError(title=page_in.title, message=str(e), code="index_failed"))

        if payload.pages and failed == len(payload.pages):
            raise HTTPException(
                status_code=502,
                detail=error_envelope(
                    "All pages failed to ingest — see errors[] for the per-page reasons.",
                    "api_error", "ingestion_failed",
                ),
            )

        return IngestResponse(
            indexed=indexed, skipped=skipped, failed=failed,
            page_urls=page_urls, errors=errors,
        )
    finally:
        conn.close()


@router.post("/v1/ingest/pages", response_model=IngestResponse)
def ingest_pages(payload: IngestRequest, _auth: None = Depends(require_api_key)):
    allowed = _allowed_categories()
    if allowed and payload.category not in allowed:
        raise HTTPException(
            status_code=403,
            detail=error_envelope(
                f"category {payload.category!r} is not in HDP_INGEST_ALLOWED_CATEGORIES",
                "invalid_request_error", "category_not_allowed", param="category",
            ),
        )

    retry_after = _rate_limiter.check(API_KEY)
    if retry_after is not None:
        raise HTTPException(
            status_code=429,
            headers={"Retry-After": str(int(retry_after) + 1)},
            detail=error_envelope(
                "Rate limit exceeded — see HDP_INGEST_API_RATE_PER_MIN.",
                "rate_limit_error", "rate_limit_exceeded",
            ),
        )

    try:
        lock_file = ingest.acquire_lock()
    except ingest.IngestLockHeld:
        raise HTTPException(
            status_code=429,
            headers={"Retry-After": "5"},
            detail=error_envelope(
                "An ingestion is already in progress — only one runs at a time (D4).",
                "rate_limit_error", "ingestion_in_progress",
            ),
        )
    try:
        return _do_ingest(payload)
    finally:
        lock_file.close()
