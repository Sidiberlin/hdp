"""4.3 — the search leg. The gap Wave 3 deliberately left open.

Wave 3's T3 profile omits `mediawiki-jobrunner`, and on this wiki that is the
difference between "search is configured" and "search works". BlueSpice's
ExtendedSearch does not index synchronously: `setup.sh` step 4e runs
`initBackends.php`, which creates the `bluespice_wikipage` index and enqueues
one indexing job per page, and *the job queue is what fills it*. With no
jobrunner nothing works those jobs off, the index stays empty, and every
full-text query returns nothing while `bsgOverrideESBackendHost` is perfectly
correct. `/qa` in Wave 3 recorded exactly that, and
`test_search_backend_points_at_the_opensearch_service` in test_site_config.py
stops at the configuration on purpose, with a comment pointing here.

So this file's structure follows the causal chain rather than the feature list:

    the jobrunner is executing jobs, and the indexing work is off the queue
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
import re

import pytest

pytestmark = pytest.mark.smoke

# The ExtendedSearch index. Created by initBackends.php, filled by the job
# queue.
ES_INDEX = "bluespice_wikipage"

# **This index has two document counts and they differ by an order of
# magnitude.** Measured on the Wave 4 clean box, at the same moment:
#
#   _cat/indices?h=docs.count  ->  828   Lucene docs, nested ones included
#   _count                     ->   93   top-level docs, i.e. wiki pages
#
# 93 is the number of indexed pages, and the namespace aggregation confirms it:
# ns10=44, ns12=30 (the seeded Help pages), ns102=10, ns112=3, ns0=2, ns4=2,
# ns2=1, ns1502=1. Wave 2 saw the same split and recorded it as "808 nested,
# `_search` reports 92".
#
# The floor is therefore calibrated for `_count`, which is what `os_count`
# calls. Taking it from the 808 figure is a mistake this file made once
# already: a floor of 100 failed against a healthy index holding all 30 Help
# pages. The floor's job is to separate "populated" from the 0 a missing
# jobrunner leaves, not to pin a number that moves with the seeded content.
ES_INDEX_MIN_DOCS = 50

# The Haystack RAG index, written by docker/haystack/ingest_hdp_wiki.py. This
# one *is* exact: 156 documents from 32 pages (CI clean-seed ground truth;
# a box that ran the T8 live API test measures 157 — its probe edit added a
# section and was never reverted, so treat box numbers above 156 as tainted)
# clean-box runs from an empty OpenSearch. See wave-progress.md — the "~828"
# that used to be quoted for this index was bluespice_wikipage's number.
HDP_INDEX = "hdp_wiki"
HDP_INDEX_DOCS = 156  # CI ground truth @ 5266633e0: 158 written, 156 stored (Sequenzen collision folds 3->1, twice)

# German content namespace. 30 Help pages live here; ns 0 has two.
HELP_NS = 12

# A term the seeded Help pages actually contain — it appears in seven of the
# twelve files under docker/mediawiki/wiki-docs/. Chosen over something like
# "Wiki" so that a hit means the *content* was indexed rather than the chrome.
SEARCH_TERM = "Architektur"


def test_the_jobrunner_is_executing_jobs(jobrunner_log):
    """mediawiki-jobrunner is actually running jobs, not merely alive.

    Its healthcheck cannot tell the difference — it confirms that PID 1 is
    still bash and nothing more — and neither can the queue depth, because the
    perpetual `invokeRunner` triggers keep it in the hundreds whether the
    runner is working or dead. So the evidence is the runner's own log, where
    each completed job prints `... t=<ms> good`.

    This is the liveness half of the search leg. The next test is the
    completion half, and both are needed: a runner that executes jobs but
    fails all of them prints no `good` lines, and a runner that is not running
    at all leaves the indexing work queued.
    """
    completed = len(re.findall(r"\bt=\d+\s+good\b", jobrunner_log))
    assert completed > 0, (
        "mediawiki-jobrunner has not completed a single job. It reports "
        "healthy, but that healthcheck only asserts PID 1 is bash — it cannot "
        "see whether runJobs.php is doing anything. Nothing downstream of this "
        "(the search index, and therefore every full-text assertion below) can "
        "work.\nLast of the log:\n" + jobrunner_log[-1500:]
    )


def test_no_indexing_work_is_left_queued(drained_job_queue):
    """Everything the install enqueued has been worked off.

    Deliberately *not* "the queue is empty". `invokeRunner` is the job of
    `mwstake/mediawiki-component-runjobstrigger` (wired up in
    BlueSpiceFoundation/src/Foundation.php) and every execution schedules the
    next, so the total never reaches zero on a healthy wiki — 600 right after
    setup.sh and 630 ten minutes later on the Wave 4 clean box, while
    `bluespice_wikipage` already held its full 808 documents throughout.

    Waiting for zero would therefore hang for the whole budget and then fail on
    a perfectly good stack, and reading the total as an indexing backlog is
    what makes Wave 3's "500 jobs queued, index empty" note misleading: under
    T3 the index was empty because there was no jobrunner, not because those
    500 were index writes.

    What is asserted is the queue *minus* the perpetual types.
    """
    q = drained_job_queue
    assert q["end"] == 0, (
        f"{q['end']} non-perpetual job(s) are still queued after "
        f"{q['seconds']}s (timeout {q['timeout']}s). Either the jobrunner is "
        f"not keeping up, or a job type is failing and being retried forever.\n"
        f"queue by type: {q['groups']}\n"
        f"Check with: docker compose logs mediawiki-jobrunner"
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

    **Do not add `srwhat=text`.** It looks like the obvious way to ask for a
    full-text rather than a title search, and it is the one variant that
    bypasses ExtendedSearch completely, falling back to MediaWiki's core
    database search. Measured on the Wave 4 clean box, same term, same wiki:

        srwhat=text   -> ['Hauptseite']        core DB search, ignores srnamespace
        srwhat=title  -> ['Hilfe:Architektur'] ExtendedSearch
        (default)     -> ['Hilfe:Architektur'] ExtendedSearch

    while OpenSearch answers the same term directly with 25 hits, top match
    `Hilfe:Architektur`. So `srwhat=text` produced a green-looking API call
    that proved nothing about the index and returned a page from a namespace
    that was explicitly excluded. The default is also what the Search Center
    and a real user get, which is the point of the test.

    `srnamespace` is still set explicitly: under the default path it is
    honoured, and the default namespace is 0, which holds two pages while the
    content is in namespace 12.
    """
    data = wiki.api(
        action="query",
        list="search",
        srsearch=SEARCH_TERM,
        srnamespace=str(HELP_NS),
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
    """`hdp_wiki` holds the 156 documents the RAG pipeline retrieves over.

    This is the other index, and confusing the two has already cost this
    project a wave: `bluespice_wikipage` is BlueSpice's own search index and
    holds ~808 nested documents; `hdp_wiki` is written only by
    `docker/haystack/ingest_hdp_wiki.py` and holds 156 documents from 32 pages
    (namespaces 0 and 12 — `INDEXABLE_NAMESPACES`). 158 sections are written
    and 156 stored, because `Help:Diagramme/Sequenzen` repeats a `Walkthrough`
    heading three times and all three collide on the same
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
