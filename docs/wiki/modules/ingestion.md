# Module: Ingestion

[`docker/haystack/ingest_hdp_wiki.py`](../../../docker/haystack/ingest_hdp_wiki.py)
is the script that actually populates the RAG chatbot's knowledge base. It
can still be invoked manually via
`docker compose exec haystack python3 ingest_hdp_wiki.py`, and is
idempotent/safe to re-run at any time, but as of QoL6 it is **also** a
background service: the `ingest-scheduler` container
([`docker/haystack/ingest-scheduler.sh`](../../../docker/haystack/ingest-scheduler.sh))
runs it with `--missing-only` on a timer (`HDP_INGEST_INTERVAL_MIN`, default
5 minutes), and a new ingestion API
([`docker/haystack/ingest_api.py`](../../../docker/haystack/ingest_api.py))
lets an external caller push individual pages into the wiki and have them
indexed in the same call. See
[architecture.md](../architecture.md#key-design-decisions) for why this
script exists instead of the ChatBot extension's own built-in indexing job.

## Responsibilities

- Read the list of indexable wiki pages directly from MariaDB.
- Render each page's current content via the MediaWiki `action=parse` API
  (so it reflects wikitext, templates, and extensions the same way a reader
  would see it).
- Split each rendered page into sections by heading (`<h1>`–`<h6>`).
- Embed each section's text with the configured embedding provider.
- Write the resulting documents into OpenSearch's `hdp_wiki` index.

## Key Files

- [`docker/haystack/ingest_hdp_wiki.py`](../../../docker/haystack/ingest_hdp_wiki.py) — the entire ingestion pipeline in one script (~560 lines)

## Public API / Interfaces

Command-line, run inside the `haystack` container:

```bash
python3 ingest_hdp_wiki.py                    # Full reindex, configured embedder
python3 ingest_hdp_wiki.py --page "Hauptseite"  # Single page (substring match on title)
python3 ingest_hdp_wiki.py --dry-run           # Preview only, no writes
python3 ingest_hdp_wiki.py --missing-only      # Only pages whose page_id isn't already in OpenSearch
python3 ingest_hdp_wiki.py --provider remote   # Override HDP_EMBEDDING_PROVIDER for this run
python3 ingest_hdp_wiki.py --provider hf_space  # One-off fast bulk embed via a HuggingFace Space
```

`--provider` defaults to `$HDP_EMBEDDING_PROVIDER` (itself defaulting to
`local`) if not given.

## Internal Structure

1. **`get_namespace_pages(conn)`** — `SELECT` from MariaDB's `page` table:
   non-redirect pages in namespaces `[0, 5000, 5002]`
   (`INDEXABLE_NAMESPACES` — main, `Ministerium`, `Projektträger`) with
   `page_content_model = 'wikitext'` (or `NULL`).
2. **`mw_api_login()`** — logs into the MediaWiki API as `MW_ADMIN_USER`
   (default `Admin`) via `action=clientlogin`, handling the BlueSpice
   Privacy consent multi-step UI flow (auto-accepts any checkbox fields
   presented).
3. **`render_page(session, prefixed_title)`** — `action=parse` with
   `prop=text|displaytitle|categories|sections|properties|externallinks`,
   TOC/edit-section/limit-report disabled.
4. **`split_by_sections(html_text)`** — regex-splits the rendered HTML on
   `<h1>`–`<h6>` boundaries (mirroring the ChatBot extension's own
   `IndexDeepset::getRawPageContentBySections()` logic — see
   [chatbot-extension](chatbot-extension.md)), strips tags, keeps an
   "Intro" section for text before the first heading, and falls back to a
   single "Full Page" section if no headings are found.
5. **`build_metadata(parsed, page, section_name)`** — builds the same
   metadata shape the query pipeline's prompt and ranker expect:
   `title_level_1`..`title_level_5` (from splitting the prefixed title on
   `/`), `chatbotmeta` (from the page's `Chatbotmeta` semantic property, if
   set — see the `ContentDroplets` template in
   `app/extensions/ChatBot/data/Content/ContentDropletsTemplates/`),
   `display_title`, `sections`, `categories`, `namespace`/`namespace_text`,
   `page_id`, and a `uri` built from `MW_API_URL`'s host.
6. **`process_page(...)`** — renders, splits, builds one `Document` per
   section (`id` = first 32 hex chars of `sha256(page_id:section_name)`,
   making re-ingestion of an unchanged section a stable overwrite), embeds
   the batch, and writes to OpenSearch with
   `DuplicatePolicy.OVERWRITE`.
7. **`main()`** — parses CLI args, connects to MariaDB, fetches the page
   list (optionally filtered by `--page` or `--missing-only`), logs into
   MediaWiki, builds the embedder (`make_embedder()`), opens the
   `OpenSearchDocumentStore`, and processes each page with a `0.2s` delay
   between pages (API rate limiting). A single page's failure is logged and
   skipped — the run continues.

## Dependencies

- **Uses:** MariaDB (page list, via `pymysql`), the MediaWiki `action=parse`
  and `action=clientlogin` APIs (`MW_API_URL`, default
  `http://mediawiki-web:8080/w/api.php`), one of
  `LocalEmbedder`/`RemoteEmbedder`/`HFSpaceEmbedder` (see
  [embedding-providers](embedding-providers.md)), `OpenSearchDocumentStore`
  (`haystack_integrations`).
- **Used by:** the `ingest-scheduler` service (below) and
  `docker/haystack/ingest_api.py` (below) both call into this module
  directly rather than shelling out to it — `get_pages_by_title()` and
  `process_page()` are reused unchanged. It remains documented as the
  "re-run after content changes" step in
  [getting-started.md](../getting-started.md) and
  [README-DOCKER.md](../../../README-DOCKER.md) for manual/interactive use.

## The periodic scheduler (QoL6)

[`docker/haystack/ingest-scheduler.sh`](../../../docker/haystack/ingest-scheduler.sh)
is the `ingest-scheduler` container's entrypoint — a bash loop, not a Python
script, so it can decide whether to run `ingest_hdp_wiki.py` at all before
paying for a Python interpreter start. Each cycle:

1. **Preflight (D2).** Curls OpenSearch's `_cluster/health` and
   `hdp_wiki/_count`. Anything other than "reachable and count > 0" is
   treated as "not ready yet" and logged once per state change — this is
   the guard against `get_staged_revisions()` returning `{}` (unreachable or
   nonexistent index) and `classify_pages()` reading that as "index
   everything," which would turn a 5-minute poll into an unattended full
   re-ingest the first time OpenSearch hiccups.
2. **Run.** `python3 ingest_hdp_wiki.py --missing-only --max-pages
   "$HDP_INGEST_MAX_PAGES"`, stdout/stderr inherited so
   `docker compose logs ingest-scheduler` shows each cycle verbatim.
3. **Classify the exit code.** `0` → success, resets the backoff. `75`
   (`EX_TEMPFAIL`) → the D4 lock was held by another ingestion; skip
   silently, no backoff. Anything else → a real failure; the sleep interval
   doubles, capped at `HDP_INGEST_BACKOFF_MAX_MIN`.

The three knobs
(`HDP_INGEST_INTERVAL_MIN`/`HDP_INGEST_MAX_PAGES`/`HDP_INGEST_BACKOFF_MAX_MIN`)
and the lock/backoff decision logic are pure functions inside the script,
pinned by `tests/bats/ingest_scheduler.bats` the same way
`tests/bats/gpu_wheel.bats` pins `install.sh`'s driver-selection function.

## The ingestion API (QoL6)

[`docker/haystack/ingest_api.py`](../../../docker/haystack/ingest_api.py)
is a FastAPI `APIRouter` mounted onto `hdp_api_server.py`
(`POST /v1/ingest/pages`) — see
[haystack-pipeline](haystack-pipeline.md) for that file's other routes.
Unlike the scheduler, it does not shell out to `ingest_hdp_wiki.py` at all:
it calls `get_pages_by_title()` and `process_page()` directly, in-process,
synchronously, so the response's `indexed`/`skipped`/`failed` counts are
real return values rather than something scraped from a log line.

- **Auth** is a `Depends()` dependency scoped to this router only — the
  existing `/hdp_pipeline/run`, `/health` and `/ready` routes stay
  unauthenticated, matching what `chatbot-proxy` already expects. Fails
  closed: an unset `HDP_INGEST_API_KEY` answers `503`, never "open".
- **Attribution** goes through a dedicated `HDPIngestBot` account (created
  by `docker/setup.sh` when `HDP_INGEST_BOT_PASSWORD` is set), falling back
  to the wiki admin account with a `WARNING` log line otherwise —
  `mw_api_login()` above grew optional `user`/`password` arguments for
  exactly this.
- **Concurrency** goes through the same `acquire_lock()`/D4 flock every
  other entry point uses, so a request and a scheduler cycle (or a manual
  run) never race each other regardless of which container either one is
  in — the request answers `429 ingestion_in_progress` instead of blocking.

Full request/response shape, the OpenAI-style error table, and the
operational limits are in
[README-DOCKER.md](../../../README-DOCKER.md#ingestion-api).

## Notable Patterns / Gotchas

- **Not triggered by page saves.** Unlike the ChatBot extension's own
  (inert, in this deployment) `UpdateIndexTable`/`IndexDeepset` pipeline,
  this script has no hook into MediaWiki's edit flow. Content is stale in
  the chatbot until someone re-runs it — `--missing-only` makes routine
  re-runs cheap by skipping already-indexed `page_id`s.
- **TLS verification disabled** when querying OpenSearch's aggregation
  endpoint directly in `get_indexed_page_ids()` (`ssl.CERT_NONE`) — matches
  `OpenSearchDocumentStore(verify_certs=False)` used elsewhere in this
  file; safe only because OpenSearch is reachable exclusively on the
  internal Docker network.
- **Deterministic document IDs.** Because `doc_id` is a hash of
  `page_id:section_name` (not a random UUID), re-ingesting an unchanged
  page produces identical IDs and `DuplicatePolicy.OVERWRITE` makes the
  write a no-op update rather than a duplicate — but if a page's *section
  headings change* (renamed/added/removed), the old sections' documents
  are **not** deleted, only new ones are added/overwritten, so a full
  reindex (not `--missing-only`) is needed to fully reflect heading
  restructuring. There is no explicit stale-section cleanup in this
  script.
- **`--missing-only` only checks page-level presence**, via an OpenSearch
  terms aggregation on `page_id` (capped at 1000 buckets) — it does not
  detect a page whose *content* changed but which already has at least one
  document indexed; use a full reindex (no flag) after editing existing
  pages, `--missing-only` is for resuming an interrupted first run.
- **HTML stripping is hand-rolled** (`HTMLStripper(HTMLParser)`), explicitly
  written as "equivalent to PHP `strip_tags()`" to match the ChatBot
  extension's own PHP-side section splitting as closely as possible.
