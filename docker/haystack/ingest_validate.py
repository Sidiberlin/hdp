# SPDX-License-Identifier: GPL-3.0-only
# Copyright (C) 2026 Sidiberlin
"""Pure helpers for docker/haystack/ingest_api.py — the ingestion API's
title/category validation, its idempotent category tag, its OpenAI-shaped
error envelope, and its rate limiter.

Wave-2 pattern (see wikitext.py, ingest_select.py): stdlib-only, so
tests/unit/test_ingest_api.py can pin this logic without FastAPI, pydantic,
requests, pymysql or haystack being installed — none of which tests/unit's
tier installs (it runs `pip install pytest` and nothing else). ingest_api.py
imports everything in this file and adds the impure half on top: the
FastAPI router, the pydantic request/response models, and the MediaWiki/
OpenSearch calls.
"""
import re
import threading
import time

_TITLE_FORBIDDEN_CHARS = set(":#<>[]|{}")


def validate_title(title: str):
    """None when `title` is an acceptable main-namespace page title (D12);
    otherwise a human-readable reason the caller rejects it with.

    Main namespace only, v1 (R6 in the plan): the colon rejection is what
    stops a caller writing into MediaWiki:/Template:/Help: by prefixing a
    title — it also rejects a legitimate title like "Berlin: eine Stadt",
    which is a known, documented limitation (create such pages in the wiki
    UI instead; see README-DOCKER.md's Ingestion API section).
    """
    if not title:
        return "title must not be empty"
    if len(title.encode("utf-8")) > 255:
        return "title must not exceed 255 bytes"
    if title != title.strip("_"):
        return "title must not start or end with '_'"
    bad = _TITLE_FORBIDDEN_CHARS & set(title)
    if bad:
        return f"title must not contain any of {sorted(bad)!r}"
    return None


_CATEGORY_FORBIDDEN_CHARS = set("[]|#<>{}")


def validate_category(category: str):
    """None when `category` is acceptable; otherwise a reason."""
    if not category:
        return "category must not be empty"
    if len(category.encode("utf-8")) > 255:
        return "category must not exceed 255 bytes"
    bad = _CATEGORY_FORBIDDEN_CHARS & set(category)
    if bad:
        return f"category must not contain any of {sorted(bad)!r}"
    return None


def ensure_category(content: str, category: str) -> str:
    """Append `[[Category:<category>]]` to `content` unless it is already
    tagged with that category — idempotent (D12: "a content string that
    already has the tag is returned unchanged"), so re-POSTing the same
    page never doubles the tag. Matches any of the three colon spellings
    MediaWiki accepts (`Category:x`, `Category : x`, with an optional
    sort-key `|...`).
    """
    tag_re = re.compile(
        r"\[\[\s*[Cc]ategory\s*:\s*" + re.escape(category) + r"\s*(\|[^\]]*)?\]\]"
    )
    if tag_re.search(content):
        return content
    if not content:
        return f"[[Category:{category}]]\n"
    sep = "\n\n" if not content.endswith("\n") else "\n"
    return f"{content}{sep}[[Category:{category}]]\n"


def error_envelope(message: str, error_type: str, code: str, param: str = None) -> dict:
    """OpenAI's error body shape, exactly — the one thing every status this
    API returns has in common."""
    return {"error": {"message": message, "type": error_type, "param": param, "code": code}}


class RateLimiter:
    """At most `limit` calls per 60s, per key. A dict keyed by the bearer
    token — there is one valid key today, but scoping by key rather than
    globally is free and correct if that ever changes.

    Thread-safe: uvicorn can run sync route handlers across worker threads.
    """

    def __init__(self, limit: int, window_seconds: float = 60.0):
        self.limit = limit
        self.window = window_seconds
        self._hits: dict = {}
        self._lock = threading.Lock()

    def check(self, key: str):
        """None when the call is allowed (and recorded); otherwise the
        number of seconds until the caller should retry."""
        now = time.monotonic()
        with self._lock:
            hits = [t for t in self._hits.get(key, []) if now - t < self.window]
            if len(hits) >= self.limit:
                self._hits[key] = hits
                return max(self.window - (now - hits[0]), 1.0)
            hits.append(now)
            self._hits[key] = hits
            return None
