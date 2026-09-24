"""Tests for docker/haystack/ingest_validate.py — the ingestion API's pure
half: title/category validation, the idempotent category tag, and the
OpenAI-shaped error envelope.

Standard library only, matching ingest_validate.py itself — no FastAPI, no
pydantic, no requests, no pymysql, no haystack. ingest_api.py (the impure
half: the FastAPI router and the pydantic models) is NOT imported here on
purpose; it pulls in dependencies tests/unit's tier does not install (see
scripts/ci/pytest.sh's run_unit — pytest only). That module is covered by
the container tier instead (tests/api/, run inside the haystack image).
"""
import pytest
from ingest_validate import (
    ensure_category,
    error_envelope,
    validate_category,
    validate_title,
)

# ─── validate_title ──────────────────────────────────────────────────


def test_valid_title_passes():
    assert validate_title("Backup-Konzept") is None


def test_empty_title_rejected():
    assert validate_title("") is not None


def test_title_over_255_bytes_rejected():
    assert validate_title("a" * 256) is not None


def test_title_at_255_bytes_is_ok():
    assert validate_title("a" * 255) is None


@pytest.mark.parametrize("bad_char", [":", "#", "<", ">", "[", "]", "|", "{", "}"])
def test_title_rejects_forbidden_chars(bad_char):
    assert validate_title(f"Page{bad_char}Name") is not None


def test_title_rejects_leading_underscore():
    assert validate_title("_Page") is not None


def test_title_rejects_trailing_underscore():
    assert validate_title("Page_") is not None


def test_title_allows_interior_underscore():
    assert validate_title("Page_Name") is None


def test_title_with_colon_documented_limitation():
    """R6: a legitimate title containing a colon is rejected — this is a
    known, documented v1 limitation, not a bug."""
    assert validate_title("Berlin: eine Stadt") is not None


def test_title_rejects_namespace_prefix_attempt():
    """The colon rejection is what stops a caller writing into a
    non-main namespace by prefixing a title."""
    assert validate_title("MediaWiki:Sidebar") is not None
    assert validate_title("Template:Infobox") is not None
    assert validate_title("Help:Something") is not None


# ─── validate_category ───────────────────────────────────────────────


def test_valid_category_passes():
    assert validate_category("Betriebshandbuch") is None


def test_empty_category_rejected():
    assert validate_category("") is not None


def test_category_over_255_bytes_rejected():
    assert validate_category("a" * 256) is not None


@pytest.mark.parametrize("bad_char", ["[", "]", "|", "#", "<", ">", "{", "}"])
def test_category_rejects_forbidden_chars(bad_char):
    assert validate_category(f"Cat{bad_char}Name") is not None


def test_category_allows_colon():
    """Categories are not titles — a colon is not forbidden here (unlike
    validate_title), since a category name is never itself a page title
    with a namespace prefix to spoof."""
    assert validate_category("Sub:Category") is None


# ─── ensure_category ─────────────────────────────────────────────────


def test_ensure_category_appends_when_absent():
    result = ensure_category("Some content.", "Betriebshandbuch")
    assert "[[Category:Betriebshandbuch]]" in result
    assert result.startswith("Some content.")


def test_ensure_category_idempotent_when_already_present():
    content = "Some content.\n\n[[Category:Betriebshandbuch]]\n"
    assert ensure_category(content, "Betriebshandbuch") == content


def test_ensure_category_idempotent_with_sort_key():
    content = "Some content.\n\n[[Category:Betriebshandbuch|B]]\n"
    assert ensure_category(content, "Betriebshandbuch") == content


def test_ensure_category_idempotent_with_spacing_variant():
    content = "Some content.\n\n[[ category : Betriebshandbuch ]]\n"
    assert ensure_category(content, "Betriebshandbuch") == content


def test_ensure_category_does_not_match_a_different_category():
    content = "Some content.\n\n[[Category:Other]]\n"
    result = ensure_category(content, "Betriebshandbuch")
    assert "[[Category:Betriebshandbuch]]" in result
    assert "[[Category:Other]]" in result


def test_ensure_category_on_empty_content():
    result = ensure_category("", "Betriebshandbuch")
    assert result == "[[Category:Betriebshandbuch]]\n"


def test_ensure_category_appends_with_blank_line_separator():
    result = ensure_category("Line one.", "X")
    assert result == "Line one.\n\n[[Category:X]]\n"


def test_ensure_category_content_already_ending_in_newline():
    result = ensure_category("Line one.\n", "X")
    assert result == "Line one.\n\n[[Category:X]]\n"


# ─── error_envelope ───────────────────────────────────────────────────


def test_error_envelope_shape():
    env = error_envelope("bad thing", "invalid_request_error", "invalid_api_key")
    assert set(env.keys()) == {"error"}
    assert set(env["error"].keys()) == {"message", "type", "param", "code"}
    assert env["error"]["message"] == "bad thing"
    assert env["error"]["type"] == "invalid_request_error"
    assert env["error"]["code"] == "invalid_api_key"
    assert env["error"]["param"] is None


def test_error_envelope_with_param():
    env = error_envelope("bad category", "invalid_request_error", "category_not_allowed", param="category")
    assert env["error"]["param"] == "category"
