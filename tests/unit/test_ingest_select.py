"""Tests for docker/haystack/ingest_select.py — the --missing-only policy.

This module decides WHICH pages (re-)ingest. A wrong decision does not
raise: ingestion exits 0 with a document count while the index silently
drifts behind the wiki — the same failure mode that pins wikitext.py, and
the reason this logic is stdlib-only and unit-pinned rather than inlined
into the ingest script.

Standard library only.
"""
from ingest_select import classify_pages, max_revision_per_page, truncate_to_max_pages


def page(pid, latest):
    return {"page_id": pid, "page_latest": latest, "page_title": f"Page{pid}"}


# ─── max_revision_per_page ──────────────────────────────────────────


def test_max_revision_takes_the_newest_bucket():
    buckets = [("7", "100"), ("7", "205"), ("9", "300")]
    assert max_revision_per_page(buckets) == {"7": "205", "9": "300"}


def test_max_revision_is_string_compare_safe():
    """Revisions arrive as ints from numeric mappings and strings from
    keyword mappings. String comparison of equal-width numbers is exact;
    mixed int/str input must be normalized, not compared (int < str raises
    or silently misorders depending on direction)."""
    buckets = [(7, 205), ("7", "100")]
    assert max_revision_per_page(buckets) == {"7": "205"}


def test_max_revision_skips_null_revisions():
    """A bucket with an unreadable revision (missing field) must not poison
    the page's max — the other buckets still speak for the page."""
    buckets = [("7", None), ("7", "88")]
    assert max_revision_per_page(buckets) == {"7": "88"}


def test_max_revision_empty_input():
    assert max_revision_per_page([]) == {}
    assert max_revision_per_page(None) == {}


# ─── classify_pages ─────────────────────────────────────────────────


def test_new_edited_unchanged_split():
    pages = [
        page(1, "100"),  # not in index -> new
        page(2, "205"),  # index has 200 -> edited
        page(3, "300"),  # index has 300 -> unchanged
        page(4, "299"),  # index has 300 (stale future-proof) -> unchanged
    ]
    staged = {"2": "200", "3": "300", "4": "300"}
    to_index, counts = classify_pages(pages, staged)
    assert [p["page_id"] for p in to_index] == [1, 2]
    assert counts == {"new": 1, "edited": 1, "unchanged": 2}


def test_empty_staged_map_indexes_everything():
    """{} means the index says nothing about revisions: a first run, or an
    index built before the field existed. Both must classify as new — the
    one-time heal — never as unchanged."""
    pages = [page(1, "100"), page(2, "200")]
    to_index, counts = classify_pages(pages, {})
    assert len(to_index) == 2
    assert counts == {"new": 2, "edited": 0, "unchanged": 0}


def test_pages_missing_revision_stay_new():
    """A page absent from the staged map is new, even when other pages have
    entries — per-page, not per-index."""
    pages = [page(1, "100")]
    staged = {"2": "200"}
    to_index, _ = classify_pages(pages, staged)
    assert [p["page_id"] for p in to_index] == [1]


def test_int_and_str_ids_compare_equal():
    """MariaDB hands out int page_ids; the staged map is keyed on the string
    form OpenSearch returns. The policy must bridge that, or every page
    looks new and the incremental run degenerates into a full re-ingest."""
    pages = [{"page_id": 7, "page_latest": "100", "page_title": "P"}]
    staged = {"7": "100"}
    _, counts = classify_pages(pages, staged)
    assert counts["unchanged"] == 1


def test_lower_latest_than_staged_is_unchanged():
    """A page whose page_latest is somehow older than what is staged (DB
    restore) must not re-ingest: the index already holds that content."""
    pages = [page(1, "50")]
    _, counts = classify_pages(pages, {"1": "100"})
    assert counts["unchanged"] == 1


def test_result_order_preserves_page_order():
    pages = [page(9, "1"), page(2, "2"), page(5, "3")]
    to_index, _ = classify_pages(pages, {})
    assert [p["page_id"] for p in to_index] == [9, 2, 5]


# ─── truncate_to_max_pages (D3) ─────────────────────────────────────


def test_truncate_slices_after_the_fact():
    """The slice is a pure list truncation — it does not touch the counts
    classify_pages already produced, so the log line built from those
    counts still describes the whole wiki, not the truncated run."""
    pages = [page(i, "100") for i in range(1, 51)]  # 50 new pages
    to_index, counts = classify_pages(pages, {})
    assert counts == {"new": 50, "edited": 0, "unchanged": 0}

    truncated = truncate_to_max_pages(to_index, 10)
    assert len(truncated) == 10
    assert [p["page_id"] for p in truncated] == list(range(1, 11))
    # The classification counts are a separate object, untouched by the
    # truncation that happened after they were computed.
    assert counts == {"new": 50, "edited": 0, "unchanged": 0}


def test_truncate_preserves_order():
    to_index = [page(9, "1"), page(2, "2"), page(5, "3"), page(1, "4")]
    truncated = truncate_to_max_pages(to_index, 2)
    assert [p["page_id"] for p in truncated] == [9, 2]


def test_truncate_noop_when_under_the_cap():
    to_index = [page(1, "1"), page(2, "2")]
    assert truncate_to_max_pages(to_index, 25) == to_index


def test_truncate_noop_at_exactly_the_cap():
    to_index = [page(i, "1") for i in range(5)]
    assert truncate_to_max_pages(to_index, 5) == to_index


def test_truncate_zero_means_unlimited():
    """--max-pages 0 is an explicit operator override for an unbounded
    catch-up run, not an accidental 'index nothing'."""
    to_index = [page(i, "1") for i in range(100)]
    assert truncate_to_max_pages(to_index, 0) == to_index


def test_truncate_negative_means_unlimited():
    to_index = [page(1, "1")]
    assert truncate_to_max_pages(to_index, -1) == to_index


def test_truncate_none_means_unlimited():
    to_index = [page(1, "1")]
    assert truncate_to_max_pages(to_index, None) == to_index


def test_truncate_empty_input():
    assert truncate_to_max_pages([], 25) == []
