"""4.3 — the search leg. The gap Wave 3 deliberately left open.

Wave 3's T3 profile omits `mediawiki-jobrunner`, and on this wiki that is the
difference between "search is configured" and "search works". BlueSpice's
ExtendedSearch does not index synchronously: `setup.sh` step 4e runs
`initBackends.php`, which creates the `bluespice_wikipage` index and enqueues
one indexing job per page, and *the job queue is what fills it*. With no
jobrunner the queue sits at ~500, the index stays empty, and every full-text
query returns nothing while `bsgOverrideESBackendHost` is perfectly correct.
`/qa` in Wave 3 recorded exactly that, and
`test_search_backend_points_at_the_opensearch_service` in test_site_config.py
stops at the configuration on purpose, with a comment pointing here.

So this file's structure follows the causal chain rather than the feature list:

    the jobrunner drains the queue
      -> bluespice_wikipage fills up
        -> OpenSearch answers a full-text query for Help content
          -> MediaWiki's list=search, which is served by ExtendedSearch, does too
            -> Special:Search — the Search Center — loads

and the last of those is the weakest assertion of the five, which is why it is
last. The Search Center is a Vue application that fetches its results over the
API after the page has loaded, so its HTML contains no results to assert on;
what the page proves is that the special page renders. The result assertion
that matters is the API one directly above it, which is the same query the
Search Center itself issues.

**The namespace trap.** `list=search` defaults to namespace 0, which holds two
pages on this wiki. The content is in namespace 12 (Help, 30 pages). A search
probe that forgets `srnamespace` returns zero hits and looks exactly like a
broken index — that cost an afternoon in Wave 2's clean-box run 1 and is
recorded in wave-progress.md as a note for future smoke tests. This is that
smoke test.
"""
import pytest

pytestmark = pytest.mark.smoke

# The ExtendedSearch index. Created by initBackends.php, filled by the job
# queue. Wave 2 and Wave 3 both measured 808 documents on a clean box — more
# than the 107 pages, because `_cat`/`_count` count nested section documents.
ES_INDEX = "bluespice_wikipage"

# Far below the 808 seen on every clean box, and far above the 0 that a missing
# jobrunner produces. The point of the floor is to separate those two states,
# not to pin a number that legitimately moves when the seeded content changes.
ES_INDEX_MIN_DOCS = 100

# The Haystack RAG index, written by docker/haystack/ingest_hdp_wiki.py. This
# one *is* exact: 153 documents from 32 pages, reproduced on three separate
# clean-box runs from an empty OpenSearch. See wave-progress.md — the "~828"
# that used to be quoted for this index was bluespice_wikipage's number.
HDP_INDEX = "hdp_wiki"
HDP_INDEX_DOCS = 153

# German content namespace. 30 Help pages live here; ns 0 has two.
HELP_NS = 12

# A term the seeded Help pages actually contain — it appears in seven of the
# twelve files under docker/mediawiki/wiki-docs/. Chosen over something like
# "Wiki" so that a hit means the *content* was indexed rather than the chrome.
SEARCH_TERM = "Architektur"


def test_the_jobrunner_drains_the_job_queue(drained_job_queue):
    """mediawiki-jobrunner empties the queue a fresh install leaves behind.

    This is the assertion Wave 3 could not make. `initBackends.php` enqueues
    the ExtendedSearch indexing work rather than doing it, so on a fresh stack
    the queue starts in the hundreds; the jobrunner container is a bash loop
    around `runJobs.php` and works it down to zero.

    A non-zero start count is asserted too. If the queue were already empty
    when the fixture began waiting, "drained" would be true of a stack whose
    jobrunner is dead, and the rest of this file would be testing an index that
    somebody else's run happened to fill.
    """
    q = drained_job_queue
    assert q["start"] > 0, (
        f"the job queue was already empty ({q['start']}) before the wait "
        f"started, so nothing here proves mediawiki-jobrunner is alive. On a "
        f"freshly installed wiki setup.sh leaves several hundred jobs — a zero "
        f"means either the tests are running against an old stack or "
        f"initBackends.php never enqueued anything."
    )
    assert q["end"] == 0, (
        f"the job queue still holds {q['end']} jobs after {q['seconds']}s "
        f"(started at {q['start']}, timeout {q['timeout']}s). Either "
        f"mediawiki-jobrunner is not running the queue down, or the box is "
        f"slower than the timeout allows — raise HDP_JOBQUEUE_TIMEOUT if it is "
        f"the latter. Check with: docker compose logs mediawiki-jobrunner"
    )


def test_extended_search_index_is_populated(drained_job_queue, os_count):
    """`bluespice_wikipage` holds documents once the queue has drained.

    Ordered after the drain by fixture dependency, not by luck: reading the
    count before the jobs have run is the flake that would make this file
    untrustworthy.
    """
    count = os_count(ES_INDEX)
    assert count is not None, (
        f"the {ES_INDEX!r} index does not exist. setup.sh step 4e runs "
        f"initBackends.php to create it; see "
        f"test_search_backend_points_at_the_opensearch_service for the "
        f"configuration half of this."
    )
    assert count >= ES_INDEX_MIN_DOCS, (
        f"{ES_INDEX} holds {count} documents, below the floor of "
        f"{ES_INDEX_MIN_DOCS}. A clean box measures ~808. The index exists but "
        f"is empty or nearly so, which is what a stack with no working "
        f"jobrunner looks like — the queue drained "
        f"({drained_job_queue['start']} -> {drained_job_queue['end']}), so if "
        f"that passed and this failed the jobs are failing rather than not "
        f"running: docker compose logs mediawiki-jobrunner"
    )


def test_opensearch_full_text_finds_help_namespace_content(drained_job_queue, os_json):
    """OpenSearch itself answers a full-text query against the indexed pages.

    Queried directly rather than through MediaWiki so that a failure here
    localises to the index, and a failure in the next test localises to
    MediaWiki's search integration. Together they say which of the two is
    broken; either one alone says only that "search does not work".
    """
    body = (
        '{"size": 5, "query": {"multi_match": {"query": "%s", '
        '"fields": ["*"]}}}' % SEARCH_TERM
    )
    result = os_json(f"/{ES_INDEX}/_search", method="POST", body=body)
    hits = result.get("hits", {})
    total = hits.get("total", {})
    total = total.get("value") if isinstance(total, dict) else total
    assert total, (
        f"OpenSearch found nothing for {SEARCH_TERM!r} in {ES_INDEX}, which "
        f"holds documents. The index is populated but not searchable for a "
        f"term that appears in seven of the seeded Help pages — check the "
        f"analyzer and the mapping.\n{result!r}"[:2000]
    )


def test_mediawiki_full_text_search_returns_results(drained_job_queue, wiki):
    """`list=search` returns hits — and it is ExtendedSearch answering.

    On this wiki `list=search` is served by BlueSpiceExtendedSearch's
    SearchEngine, so a hit here is an end-to-end statement: MediaWiki reached
    OpenSearch, the index was populated by the jobrunner, and the result came
    back through the same path the Search Center uses.

    `srnamespace` is set explicitly. The default is namespace 0, which holds
    two pages; the content is in namespace 12. Omitting it returns zero hits
    against a perfectly healthy index.
    """
    data = wiki.api(
        action="query",
        list="search",
        srsearch=SEARCH_TERM,
        srnamespace=str(HELP_NS),
        srwhat="text",
        srlimit="10",
    )
    results = data.get("query", {}).get("search", [])
    assert results, (
        f"full-text search for {SEARCH_TERM!r} in namespace {HELP_NS} returned "
        f"no results, although OpenSearch answers the same query directly. "
        f"MediaWiki is not reaching the index it is configured for — check "
        f"bsgOverrideESBackendHost and the ExtendedSearch logs.\n"
        f"{data!r}"[:2000]
    )
    titles = [r["title"] for r in results]
    assert any(t.startswith(("Hilfe:", "Help:")) for t in titles), (
        f"search returned {len(results)} results but none in the Help "
        f"namespace: {titles}. srnamespace={HELP_NS} was requested."
    )


def test_search_center_special_page_renders(drained_job_queue, wiki):
    """Special:Search loads for an authenticated user.

    The weakest of the five assertions and deliberately the last: BlueSpice
    replaces Special:Search with the Search Center, a Vue application that
    fetches results over the API *after* the HTML has been delivered. So there
    are no results in this response to assert on, and pretending otherwise
    would mean scraping a loading state. What this rules out is a 500 from the
    special page itself — the class of failure that leaves the wiki with a
    working index and no way for a human to reach it. The result assertion is
    the API test above, which is the same query this page issues.
    """
    resp = wiki.fetch("/index.php/Special:Search")
    assert resp.status == 200, (
        f"Special:Search returned HTTP {resp.status}. The index is populated "
        f"and queryable, so this is the Search Center failing to render rather "
        f"than a search problem."
    )
    assert len(resp.body) > 10 * 1024, (
        f"Special:Search returned only {len(resp.body)} bytes. The Search "
        f"Center ships a substantial ResourceLoader payload; a near-empty body "
        f"means the page rendered as an error or a bare shell."
    )


def test_haystack_index_holds_the_ingested_wiki(ingest_ran, os_count):
    """`hdp_wiki` holds the 153 documents the RAG pipeline retrieves over.

    This is the other index, and confusing the two has already cost this
    project a wave: `bluespice_wikipage` is BlueSpice's own search index and
    holds ~808 nested documents; `hdp_wiki` is written only by
    `docker/haystack/ingest_hdp_wiki.py` and holds 153 documents from 32 pages
    (namespaces 0 and 12 — `INDEXABLE_NAMESPACES`). 155 sections are written
    and 153 stored, because two sections collide on
    `sha256(page_id:section_name)`.

    Exact rather than a floor, unlike the ExtendedSearch count. The number was
    reproduced identically on three clean-box runs from an empty OpenSearch,
    and the reason to pin it is that the failure it guards against — the
    section-splitting in `wikitext.py` changing shape — moves the count without
    breaking anything loudly.
    """
    count = os_count(HDP_INDEX)
    if not ingest_ran:
        assert count is not None and count > 0, (
            f"{HDP_INDEX} is empty or absent, and nothing in this run ingested "
            f"the wiki. scripts/ci/t4-smoke.sh does that before the tests; "
            f"against a stack of your own, run:\n"
            f"  docker compose exec haystack python3 /opt/pipeline/ingest_hdp_wiki.py"
        )
        return
    assert count == HDP_INDEX_DOCS, (
        f"{HDP_INDEX} holds {count} documents, not {HDP_INDEX_DOCS}. Ingestion "
        f"ran in this job, so this is a change in what it produces rather than "
        f"a missing step — the usual cause is section splitting in "
        f"docker/haystack/wikitext.py, or a change to which namespaces "
        f"ingest_hdp_wiki.py treats as indexable."
    )
