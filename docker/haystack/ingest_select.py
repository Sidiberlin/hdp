# SPDX-License-Identifier: GPL-3.0-only
# Copyright (C) 2026 Sidiberlin
"""Pure page-selection policy for ingest_hdp_wiki.py.

Wave-2 pattern (see wikitext.py): the decision that decides WHICH pages get
(re-)indexed lives here, stdlib-only, so tests/unit can pin it without
pymysql, requests, haystack, or OpenSearch. A wrong selection does not raise
— ingestion exits 0 and reports a document count while the index silently
drifts behind the wiki. That failure mode is why this is its own module.

The policy:

    new        page_id absent from the index                -> ingest
    edited     page_latest newer than the staged revision   -> ingest
    unchanged  page_latest equals the staged revision       -> skip

"Staged revision" is the max `meta.revision` across a page's section
documents, as written by wikitext.build_metadata. Pages whose documents
carry no revision metadata (an index built before the field existed) cannot
appear in that map, so they classify as new and are re-ingested once — the
index heals itself on the first --missing-only run after an upgrade, and
every run after that is a precise increment. The same is true of any page
whose revision could not be read back, rather than silently assuming it is
up to date: an unreadable state must not suppress an ingest.
"""


def _as_str(value) -> str:
    """Normalize a page_id/revision to the string form OpenSearch returns.

    MariaDB hands out ints; OpenSearch term buckets carry strings (or ints,
    depending on how the field was mapped and queried). Comparing across
    that boundary with == is how a stale page silently looks current.
    """
    return str(value)


def max_revision_per_page(buckets) -> dict:
    """Collapse composite-agg buckets [(page_id, revision), ...] to the
    newest staged revision per page_id.

    A page can legitimately have documents at two revisions in the index:
    an edit that renamed a section writes the new section doc_ids while the
    old section document lingers (deletion sync is not this feature). Taking
    the max is what makes the comparison stable — min would re-ingest that
    page on every run, because the stale bucket never goes away.
    """
    out: dict = {}
    for pid, rev in buckets or []:
        if rev is None:
            continue
        key = _as_str(pid)
        rev = _as_str(rev)
        if key not in out or rev > out[key]:
            out[key] = rev
    return out


def _is_newer(latest: str, staged: str) -> bool:
    """True when `latest` is newer than `staged`.

    Compared numerically when both sides are numeric — MediaWiki revision ids
    are, and string comparison misorders them ('50' > '100' lexicographically,
    which would re-ingest a DB-restored page forever). Falls back to string
    comparison only for exotic non-numeric values, where any total order is
    as good as any other.
    """
    if latest.isdigit() and staged.isdigit():
        return int(latest) > int(staged)
    return latest > staged


def classify_pages(pages: list, staged_revs: dict) -> tuple:
    """Split wiki pages into the list to index plus counts for the log line.

    pages:       rows from get_namespace_pages (page_id, page_latest, ...)
    staged_revs: {page_id(str): revision(str)} from the index; an empty dict
                 means "the index says nothing about revisions" — every page
                 is then treated as needing ingestion (first run after an
                 upgrade, or a genuinely empty index; both are the same
                 decision for the same reason).

    Returns (to_index, counts) with counts = {"new": n, "edited": n,
    "unchanged": n}. "unchanged" pages keep their place in the index; they
    are the only pages this function drops.
    """
    to_index = []
    counts = {"new": 0, "edited": 0, "unchanged": 0}
    for page in pages:
        pid = _as_str(page["page_id"])
        latest = _as_str(page.get("page_latest", ""))
        staged = staged_revs.get(pid)
        if staged is None:
            counts["new"] += 1
            to_index.append(page)
        elif _is_newer(latest, staged):
            counts["edited"] += 1
            to_index.append(page)
        else:
            counts["unchanged"] += 1
    return to_index, counts
