# AGENTS.md — HDP Agent/Developer Operating Manual

**Project:** BlueSpice HDP Edition (MediaWiki/BlueSpice fork + Haystack RAG chatbot, Dockerized)

This is the operating manual for AI agents and developers working on the HDP repository. All claims in this document are grounded in the actual codebase — read files before making changes.

---

## Project Orientation

HDP is a Dockerized MediaWiki/BlueSpice wiki extended with a self-hosted RAG chatbot. Editors write wiki content normally; a separate ingestion pipeline indexes that content into OpenSearch; readers ask questions via a chat widget and receive LLM-generated answers grounded in and citing the indexed wiki pages.

For full architecture documentation, see [`docs/wiki/`](docs/wiki/) (system diagram, data flow, and design decisions).

---

## Repo Map

| Directory | Purpose | Key Files |
|-----------|---------|-----------|
| `app/` | BlueSpice MediaWiki source (core + ~130 extensions) | Vendored per upstream distribution model |
| `app/settings.d/` | **BlueSpice extension loader and config** — loaded in alphanumeric order, NOT via `wfLoadExtension` in `LocalSettings.php` | `050-Fixes.php` (MariaDB mode fixes), `100-ChatBot.php` (chatbot proxy config) |
| `app/extensions/ChatBot/` | ChatBot MediaWiki extension — chat widget, REST endpoints, Deepset API client | `extension.json`, `includes/Api/ChatApi.php` |
| `app/skins/` | MediaWiki skins | `.gitignore` has `/*` **but re-includes the six shipped skins by name** — they are trackable, do not use `git add -f`. See [The `app/skins/.gitignore` Trap](#the-appskinsgitignore-trap) |
| `docker/patches/` | Patch manifest — one YAML sidecar per patch, plus the Class-A `.patch` files | 21 entries; see [`patches.md`](patches.md) |
| `docker/ci/fixtures/` | The seeded database snapshot T5 runs `update.php` against | `seeded-wiki.sql.gz` + `.meta.json`; regenerate with `scripts/ci/make-db-fixture.sh` |
| `VERSIONS.yml` | **What version this fork is** — gated against the tree by CI | see [Versions and upgrades](#versions-and-upgrades) |
| `scripts/` | Contributor and CI entry points | `check.sh` (run before pushing), `verify-patches.sh`, `apply-patches.sh`, `convert-docs.sh`, `ci/` (incl. `t3-integration.sh`, `t4-smoke.sh`, `t5-migration.sh`, `release-watch.sh`, `lib/stack.sh`), `lib/` |
| `tests/` | The test suite — see [The five pytest tiers](#the-five-pytest-tiers) | `unit/` (stdlib), `haystack/` (real haystack-ai), `integration/` (a live wiki; `smoke`-marked tests need all seven containers, `migration`-marked ones need an upgraded stack), `bats/` (shell) |
| `docker/` | Docker build contexts and setup scripts | `setup.sh` (first-boot install), `haystack/` (RAG pipeline), `chatbot-proxy/` (Deepset→Haystack adapter) |
| `docker/haystack/` | Haystack RAG pipeline implementation | `hdp_pipeline.yaml`, `ingest_hdp_wiki.py`, `hdp_api_server.py`, `entrypoint.sh`, plus the two Wave 2 extractions `wikitext.py` (pure transforms) and `serialization.py` (`to_native`, `load_pipeline`) |
| `docs/` | User and architecture documentation | `QA-REPORT.md`, `embedding-providers.md`, `dev/upgrade-runbook.md` (how to take an upstream release), `wiki/` (technical wiki) |
| `docker-compose.yml` | All 7 services: MariaDB, MediaWiki (PHP-FPM + Apache + jobrunner), OpenSearch, Haystack, chatbot-proxy | |
| `.env.example` | Environment variable template (Infisical + plaintext fallback) | |
| `README-DOCKER.md` | Quick start and troubleshooting | |

---

## Environment & Setup

### Prerequisites

- Docker and Docker Compose
- Ports `8080` (wiki), `1416` (hayhooks admin), `1417` (RAG query API) available on host

### First-Time Setup

```bash
# 1. Configure environment
cp .env.example .env
# Edit .env — at minimum set the *_PASSWORD variables and HDP_LLM_API_KEY
# (or configure Infisical for secret management)

# 2. Build and start all services
docker compose up -d --build

# 3. Wait for MariaDB and OpenSearch to be healthy
docker compose ps
# mariadb and opensearch should show "healthy"; this can take 30-60s

# 4. Run first-boot setup (installs MediaWiki + ~130 BlueSpice extensions)
docker compose exec mediawiki bash /setup.sh

# 5. Open the wiki
open http://localhost:8080/w/
```

**Login:** `Admin` / (the `HDP_ADMIN_PASSWORD` you set in `.env`)

### Key Environment Variables

| Variable | Purpose | Secret? |
|----------|---------|---------|
| `MW_DOCKER_PORT` | Wiki host port (default: 8080) | No |
| `HDP_LLM_BASE_URL` | LLM API endpoint | No |
| `HDP_LLM_MODEL` | LLM model name | No |
| `HDP_LLM_API_KEY` | LLM API key | **Yes** |
| `HDP_EMBEDDING_PROVIDER` | `local` \| `remote` \| `hf_space` | No |
| `HDP_EMBEDDING_MODEL` | Embedding model name | No |
| `HDP_EMBEDDING_BASE_URL` | Remote embedding endpoint (if `remote`) | No |
| `HDP_EMBEDDING_API_KEY` | Remote embedding key (if `remote`) | **Yes** |
| `HDP_DB_PASSWORD` | MariaDB bluespice user password | **Yes** |
| `HDP_ADMIN_PASSWORD` | Wiki Admin account password | **Yes** |
| `HDP_OPENSEARCH_PASSWORD` | OpenSearch admin password (8+ chars, required) | **Yes** |
| `INFISICAL_*` | Infisical machine identity (optional) | **Yes** |

### Credentials Model

- **Infisical (recommended):** Set `INFISICAL_URL`, `INFISICAL_PROJECT_ID`, `INFISICAL_CLIENT_ID`, `INFISICAL_CLIENT_SECRET`. At container start, `infisical-loader.sh` fetches every secret whose name starts with `HDP_` and injects it into the environment, overriding `.env` values.
- **Plaintext `.env` fallback:** If `INFISICAL_CLIENT_ID`/`SECRET` are left blank, `infisical-loader.sh` is a no-op and `.env` values are used as-is.

---

## How to Run / Verify Things

### Verify Stack is Running

```bash
docker compose ps
# All services should show "healthy" (mediawiki, mariadb, opensearch, haystack, chatbot-proxy)
```

### Create Wiki Content

```bash
# Create a test page
docker compose exec mediawiki php maintenance/run.php edit.php \
  --user Admin \
  --summary "Test page for chatbot ingestion" \
  --no-rc \
  "TestPage" < \
  <(echo "This is a test page about Cloud computing models: IaaS, PaaS, and SaaS.")
```

### Run Ingestion

```bash
# Full reindex of all wiki pages
docker compose exec haystack python3 ingest_hdp_wiki.py

# Only pages not already in OpenSearch (fast resume)
docker compose exec haystack python3 ingest_hdp_wiki.py --missing-only

# Single page
docker compose exec haystack python3 ingest_hdp_wiki.py --page "TestPage"

# Preview without writing
docker compose exec haystack python3 ingest_hdp_wiki.py --dry-run
```

### Verify OpenSearch Index

```bash
# Check document count
docker compose exec opensearch curl -sk -u "admin:${HDP_OPENSEARCH_PASSWORD}" \
  "https://localhost:9200/hdp_wiki/_count"
```

### Test Chatbot Directly (via REST API)

**Method 1: Wiki REST endpoint (requires login cookie)**

```bash
# The ChatBot extension expects a MediaWiki login cookie. Use browser testing for this.
# Endpoint: GET /w/rest.php/bmbf/chat?query=...&sessionId=...&followUpType=...
```

**Method 2: Direct Haystack pipeline (bypasses wiki UI)**

```bash
docker compose exec haystack curl -s -X POST http://localhost:1417/hdp_pipeline/run \
  -H "Content-Type: application/json" \
  -d '{"question":"What are the cloud computing models?","query":"What are the cloud computing models?","path":"rag"}'
```

---

## Before You Push

One command, no toolchain install, no `.env`, no running stack:

```bash
./scripts/check.sh            # everything; ~100s
./scripts/check.sh --fix      # apply auto-fixes where the tool supports one
./scripts/check.sh --only ruff
./scripts/check.sh --patches  # + full patch verification (needs a composer-installed tree)
```

It returns the same verdict as the CI lint stage. Each check prefers a binary
already on your PATH and otherwise runs the same pinned image CI uses, so there
is nothing to install.

**A skipped check is not a passing check.** Anything that can run neither way is
reported `SKIP`, listed again in the summary, and the closing line says
explicitly that this is not the full CI verdict. CI will still run it.

What it covers today:

| Check | What it catches |
|---|---|
| `shellcheck` | shell in `docker/ scripts/ hdp.sh` (not vendored upstream) |
| `yamllint` | compose, publiccode, pipeline, CI, the patch manifest |
| `ruff` | Python under `docker/`, `scripts/` and `tests/`, against the pinned ruleset in `ruff.toml` |
| `pytest-unit` | `tests/unit/` — stdlib-only unit tests, ~2s, no install needed |
| `pytest-haystack` | `tests/haystack/` — real `haystack-ai`, no mocks (see below) |
| `integration` | `tests/integration/` — a live wiki serving real traffic (`--integration`) |
| `smoke` | the same directory unfiltered — search and the chatbot, on all seven containers (`--smoke`) |
| `bats` | `docker/infisical-loader.sh` behaviour, incl. the two Wave 0 security fixes |
| `php-lint` | the 17 `app/settings.d/*.php` files gating ~130 extensions |
| `compose` | `docker compose config` on v2 |
| `gitleaks` | committed secrets, scoped to paths this project authors |
| `env-example` | every no-default `${VAR}` compose uses is in `.env.example` |
| `publiccode` | `publiccode.yml` schema — openCode validates this file |
| `patch-ignore` | no patch target is swallowed by a `.gitignore` rule |
| `manifest` | the patch manifest's schema and target paths |
| `versions` | ⭐ `VERSIONS.yml` still matches the tree — see [Versions and upgrades](#versions-and-upgrades) |
| `composer-audit` | ⭐ a **new** CVE in `app/composer.lock` (the 34 known ones are baselined) |
| `fresh-clone` | ⭐ every input `setup.sh` needs is actually committed |
| `patches` | all 21 patches are in the tree (`--patches`) |

`fresh-clone` is the one worth understanding. `docs/QA-REPORT.md` records seven
bugs, six of them critical, and notes that each *"was invisible in the
development checkout … and only surfaced on a genuinely fresh clone."* It clones
the repo at HEAD into a throwaway directory and asserts the committed tree is
complete — which is the only way to catch a file that exists on your disk but
was never committed, or that a `.gitignore` rule silently refuses.

Note that it tests **committed** state. Uncommitted work in your tree is
deliberately not under test, and it says so when your tree is dirty.

### The five pytest tiers

Tests live in `tests/` at the repo root, not inside `docker/haystack/`. Those
directories are Docker build contexts; a test placed there either ships inside
the production image or needs `.dockerignore` surgery.

| Tier | Path | Needs | Cost |
|---|---|---|---|
| `pytest-unit` | `tests/unit/` | nothing but pytest | ~2s |
| `pytest-haystack` | `tests/haystack/` | `haystack-ai` + `envsubst` | 0s warm, ~47s cold |
| `integration` | `tests/integration/` | a running, installed wiki + docker | ~50s against a live stack |
| `smoke` | `tests/integration/`, `-m "not migration"` | **all seven** containers, a drained job queue, an ingested index | ~25 min from nothing |
| `migration` | `tests/integration/`, `-m migration` | a stack `scripts/ci/t5-migration.sh` has upgraded | ~7 min from nothing |

The first split follows what the code actually imports. `render_pipeline.render`,
`build_result_from_haystack` and the `wikitext` pure functions import nothing
outside the standard library, so they run on bare Python. Only `to_native()`
and `load_pipeline()` need Haystack.

The last three are a different kind of thing: they assert on a wiki that is
actually serving traffic. They are also all the *same directory*, separated by
marker — so T4 is a strict superset of T3 rather than a second suite to keep in
step, and T5 adds a third without a third conftest or a third wiki client. See
[The integration tier](#the-integration-tier),
[The smoke tier](#the-smoke-tier-t4) and
[The migration tier](#the-migration-tier-t5) below.

`migration` is excluded from both of the others on purpose: those assertions
only mean anything after the seeded snapshot has been loaded over the installed
database and `update.php` re-run against it. Collected anywhere else they would
be asserting against a fresh install, which is the exact thing T5 exists to
stop being the only thing tested.

**Nothing is mocked, anywhere.** That is affordable because of a measurement:

```
docker run --rm python:3.11-slim  pip install haystack-ai==2.15.0
-> 24s, 172MB site-packages, no torch, no transformers
```

torch and transformers arrive via `sentence-transformers`, a runtime dependency
of the embedder that nothing under test touches. Real `Document`,
`GeneratedAnswer`, `ExtractedAnswer` and `Answer` objects cost 24 seconds.

Run them with `scripts/ci/pytest.sh`, which knows how to satisfy each tier:

```bash
scripts/ci/pytest.sh                  # unit + haystack
scripts/ci/pytest.sh --tier unit      # the fast one
scripts/ci/pytest.sh --tier smoke     # the full stack, T4's assertions
scripts/ci/pytest.sh --tier migration # T5's assertions, after t5-migration.sh
scripts/ci/pytest.sh -- -k to_native  # args after -- go to pytest
```

`--tier all` is unit + haystack, and deliberately not integration or smoke:
those two run on any checkout with no `.env` and no containers, which is what
lets `check.sh` promise a CI-equivalent verdict without asking anyone to boot
the stack.

Inside the running stack, the haystack image ships `pytest`, so:

```bash
docker compose run --rm --no-deps --entrypoint python \
    -v "$PWD:/w:ro" -w /w haystack \
    -m pytest tests/unit tests/haystack -p no:cacheprovider
```

`--entrypoint python` is not optional. The image sets
`ENTRYPOINT ["/opt/pipeline/entrypoint.sh"]`, and that script never `exec "$@"` —
it ignores the command entirely and boots hayhooks. Without the override the
command does not run pytest at all; hayhooks starts instead and dies on
`OSError: [Errno 30] Read-only file system: '/w/pipelines'`, because `-w /w`
makes it try to create its pipelines dir inside the read-only mount.

Mount the **repo root**, not just `tests/`. `pytest.ini` lives at the root and is
what sets `pythonpath`, and one test resolves the repo root by walking up for
`docker-compose.yml`. Mounting only `tests/` gives 39 passed and 1 error.
`-p no:cacheprovider` is because the mount is read-only and pytest otherwise
tries to write `.pytest_cache` into it.

### Golden files

`build_result_from_haystack` is covered by 16 checked-in JSON fixtures under
`tests/unit/fixtures/build_result/`. Regenerate them, never by hand:

```bash
scripts/ci/pytest.sh --regen-golden   # then read the diff before committing
```

Regeneration never runs in CI. A golden file that updates itself records
whatever the code does today, which is the opposite of what it is for.

The two `uuid4()` values are validated rather than mocked: the comparison walks
the whole structure, asserts anything UUID-shaped really is version 4, and then
substitutes a sentinel. Monkeypatching `uuid.uuid4` would make the comparison
simpler and would stop testing the real call — swapping it for `uuid1()`, which
encodes the host MAC address and the time into an id sent to every chat client,
would keep a monkeypatched suite green.

### The integration tier

`tests/integration/` asserts on a wiki that is running and installed. It is
what Wave 3's T3 job runs, and it is the committed form of the smoke checks
Waves 0–2 did by hand from a script in `/root`.

```bash
scripts/ci/t3-integration.sh          # the whole sequence, from nothing
scripts/ci/t3-integration.sh --keep   # ... and leave the stack up
scripts/ci/pytest.sh --tier integration   # against a stack you already have
./scripts/check.sh --integration      # ... as part of the usual check run
```

`t3-integration.sh` is the entry point worth knowing: fresh volumes → `compose
up` → `setup.sh` → `tests/integration` → teardown with `-v`. Both CI
definitions (`.github/workflows/t3-integration.yml`,
`.gitlab-ci.yml:t3-integration`) are thin callers of it, so the pipeline runs
the same code you can run in a terminal.

**Standard library only.** No `requests`, no pip install beyond pytest itself.
That is what makes T3's dependency list "docker, and the python3 the runner
already has".

Four things about this tier are load-bearing and non-obvious:

- **It must run from the host.** `$wgServer` is `http://localhost:8080`, so
  MediaWiki answers `/w/index.php/Foo` with a redirect to the canonical
  `/wiki/Foo` — a URL that resolves only through the published port mapping.
  Running the tests inside the compose network fails on the redirect. Run 1 of
  the Wave 2 clean-box validation lost an afternoon to this.
- **The API needs a login even to read.** Anonymous `meta=siteinfo` returns
  `readapidenied`. Use the `wiki` fixture, not a bare `WikiClient`.
- **A 200 is not enough.** Logged out, this wiki answers
  `Special:Preferences` with a healthy 200 login prompt. Every page assertion
  also requires MediaWiki to have rendered the page *for Admin*, and
  `test_anonymous_preferences_is_not_the_authenticated_page` is the control
  that fails if that marker ever stops distinguishing the two. Without it, a
  session that silently failed to authenticate would leave the whole file
  green while proving nothing.
- **Unreachable is a failure, not a skip.** Deciding whether a stack exists is
  `scripts/ci/pytest.sh`'s job — it probes once and returns 77, which
  `check.sh` renders as SKIP and never as a pass. Once pytest is running the
  stack is supposed to be there, so a connection refused is red. A tier that
  skips itself is a green run against a wiki that never booted.

The T3 profile is `mariadb opensearch mediawiki mediawiki-web` — the full
stack minus `mediawiki-jobrunner`, `haystack` and `chatbot-proxy`, which is
where nearly all the build time is (haystack alone is a 2.5 GB image and ~285
of the ~290 seconds a full build takes). `mediawiki-web` is not optional: QA
Bug 4 is an HTTP-level fact about a rendered page, and PHP-FPM has no HTTP
endpoint.

Those three omissions are not free, and the tier is explicit about what they
cost: with no jobrunner the ExtendedSearch index is never built (~500 jobs
stay queued and `bluespice_wikipage` stays empty), and with no haystack or
chatbot-proxy the chat path is not exercised at all. That is the gap
[the smoke tier](#the-smoke-tier-t4) closes.

Note that `t3-integration.sh` waits for a *connection*, not for health. Before
`setup.sh` runs there is no `LocalSettings.php`, so `/w/` returns 500 and
`mediawiki-web` is legitimately unhealthy — expected on any genuinely fresh
box, and not a regression.

### The smoke tier (T4)

The same directory, run without `-m "not smoke"`, against **all seven**
containers. It is what the nightly `T4 smoke` workflow runs, and it covers the
two legs T3 structurally cannot reach.

```bash
scripts/ci/t4-smoke.sh                # the whole sequence, from nothing
scripts/ci/t4-smoke.sh --keep         # ... and leave the stack up
scripts/ci/t4-smoke.sh --no-ingest    # skip the ~8 minute reindex
scripts/ci/pytest.sh --tier smoke     # against a stack you already have
./scripts/check.sh --smoke            # ... as part of the usual check run
```

`t4-smoke.sh` is T3's sequence plus three steps: wait for all seven to report
healthy, wait for `mediawiki-jobrunner` to drain the job queue, then run
`ingest_hdp_wiki.py`. Those are minutes of waiting and they are **setup, not
assertions** — they live in the script so a failure reads as "the stack could
not be brought to the state under test", while the assertions about the
resulting state live in `tests/integration/test_search.py`.

What the tier adds, and why each one needs the full stack:

- **All seven healthy** (`test_full_stack.py`). Only meaningful after
  `setup.sh`: before it there is no `LocalSettings.php`, so `/w/` 500s and
  `mediawiki-web` is legitimately unhealthy.
- **Anonymous access** — `/w/` and `Special:UserLogin` render, and no
  authenticated content leaks. Asserted on the form's own input names, not on
  the status code, because a 200 login prompt is what this wiki answers with
  when logged out.
- **Search** (`test_search.py`) — the jobrunner drains the queue, then
  `bluespice_wikipage` fills, then OpenSearch answers a full-text query, then
  MediaWiki's `list=search` does too, then `Special:Search` renders. In that
  order, by fixture dependency, so a failure localises.
- **The chatbot** (`test_chatbot.py`) — `/health` 200, `/ready` 503,
  `/chat-stream` 503, `/session` 200 without an LLM key; with one, `/ready`
  200 and a `/chat-stream` that really streams.

Four things here are load-bearing:

- **`pdf-generator` is not a service.** The Wave 4 brief lists it as one of the
  seven; docker-compose.yml has no such service. The name refers to the
  haystack container's *second port* (`HDP_PDF_PORT`, 1417), where
  `hdp_api_server.py` serves `/health`, `/ready` and the RAG query API
  alongside hayhooks on 1416. The seventh container is `mediawiki-web`.
- **chatbot-proxy is reached only through `docker compose exec`.** It publishes
  no port — the wiki reaches it over the compose network — and its image is
  `python:3.12-slim`, with no curl, wget or nc. The probes use the container's
  own python3. That it is unreachable from the host is a property worth
  keeping: a reachable proxy would be an unauthenticated RAG endpoint
  bypassing the wiki login.
- **`list=search` defaults to namespace 0**, which holds two pages. The content
  is in **namespace 12** (30 Help pages). A probe without `srnamespace`
  returns zero hits against a perfectly healthy index and looks exactly like a
  broken one.
- **Two indices, and confusing them has already cost a wave.**
  `bluespice_wikipage` is BlueSpice's own search index, ~808 documents because
  it counts nested section documents; `hdp_wiki` is written only by
  `ingest_hdp_wiki.py` and holds **156 documents from 32 pages**. The "~828"
  once quoted for `hdp_wiki` was the other index's number.

The nightly workflow adds two things `t4-smoke.sh` takes as a flag rather than
assuming: a buildx GHA layer cache and a saved HuggingFace model cache, both
configured in `docker/ci/compose.cache.yml`. That file is a CI-only overlay —
a developer's `docker compose up` must not depend on a GitHub cache backend
existing. `--cache` sets `COMPOSE_BAKE=1`, without which the build goes to the
classic builder, which does not understand `type=gha`, and succeeds while
caching nothing.

The cache is worth exactly as much as the base image tags hold still: every
layer key is chained off the resolved digest of `python:3.12-slim`,
`python:3.11-slim-bookworm` or `opensearchproject/opensearch:2.18.0`, so a
republished tag legitimately costs one nightly its whole cache. That is what
made the `cache-proof` job flaky when it read the cache the `t4` job exported
75 minutes earlier; it now seeds and reads the cache itself, seconds apart.

### The migration tier (T5)

T3 proves a **fresh install** works. Every real HDP operator does the other
thing: runs new code against a database that already holds pages, users and
permissions. None of the code that *migrates rows* is reached by an install
into an empty schema, so until Wave 5 the upgrade path had never been executed
anywhere in CI.

```bash
scripts/ci/t5-migration.sh            # the whole sequence, from nothing (~7 min)
scripts/ci/t5-migration.sh --keep     # ... and leave the stack up
scripts/ci/pytest.sh --tier migration # against a stack it already upgraded
```

The sequence: install normally, **drop the database**, load
`docker/ci/fixtures/seeded-wiki.sql.gz` — a real wiki captured before the
upgrade — and run `update.php` against that.

It installs first because the wiki cannot boot from the snapshot alone:
`app/vendor/` is gitignored and created by composer, and `LocalSettings.php` is
written by `install.php`. Committing a `LocalSettings.php` fixture would mean
committing generated secrets *and* a second source of truth for what `setup.sh`
produces.

What the tier asserts, in three groups:

- **The fixture** — sha256 matches its `.meta.json`, it was captured from the
  release `VERSIONS.yml` declares, it carries real content (≥100 pages), and
  **no password hash is in it**. That last one is a committed-artifact
  assertion: `--hex-blob` writes `user_password` as `0x3A70626B646632…`, which
  decodes to a real `:pbkdf2:` hash, so the scrub happens in a scratch database
  and the file is dumped from *that*.
- **The migration** — `update.php` exits 0, its log carries no failure
  patterns, and it *did something*: an updater pointed at the wrong database
  produces a short clean log and exit 0, which is indistinguishable from
  success unless you look for the work.
- **The wiki afterwards** — page and table counts did not shrink, a Help page
  still renders through the API, and `Special:Preferences` still loads **for a
  logged-in user**. `t5-migration.sh` resets the Admin password after the
  upgrade precisely so that assertion can exist; the fixture's hashes are
  scrubbed, and logged out this wiki answers with a healthy 200 login prompt.

Regenerating the fixture is a **manual, once-per-release** step, for the same
reason golden files are:

```bash
scripts/ci/make-db-fixture.sh   # from a running, installed stack you trust
```

Order matters at upgrade time: the fixture must still be from the **old**
release when T5 runs, so regenerate it *after* the upgrade merges. See
[`upgrade-runbook.md`](upgrade-runbook.md) step 7.

### The authentication path

`tests/integration/test_auth_path.py` is unmarked, so it runs in T3, T4 and T5.

Two of the 21 patches are different in kind from the rest:
`pluggableauth-service` and `oidc-client`. Dropping a MultimediaViewer patch is
a cosmetic regression somebody notices; dropping either of these is an
authentication regression that nothing notices. `oidc-client` is also the only
patch whose target lives under `app/vendor/` — gitignored, `rm -rf`'d on every
setup run, recreated by composer — so it has no gitignore-level protection at
all. What protects it is being re-applied on every install and asserted
afterwards.

Three layers, each catching something the others cannot: the manifest's own
verdict (`verify-patches.sh --id`, run inside the container where the installed
tree is); the patched content present **and the vulnerable upstream form
absent** in the installed file; and the code still parsing, autoloading and
serving a login form.

Both sidecars now carry an `anti:` pattern — the upstream line the patch
replaces — and `verify-patches.sh` fails a diff-mode patch whose anti-pattern
matches even when `patch` reports it as already applied. A fuzzy application
can leave both forms in the file, and for an authentication library that is a
fix nobody is using.

### Generated documentation (`convert-docs.sh`)

`docker/mediawiki/wiki-docs/*.wiki` is a **committed build product**.
`setup.sh` seeds those files, never `docs/wiki/*.md`, so editing a markdown
source without regenerating ships a wiki whose documentation disagrees with
the repo.

```bash
scripts/convert-docs.sh           # regenerate, then commit the result
scripts/convert-docs.sh --check   # diff against the committed output, change nothing
```

`--check` is what `tests/integration/test_convert_docs.py` calls. It needs
docker but not the wiki, so it is the one test in that tier that still means
something under the T3 minimal profile.

pandoc comes from a pinned image (`pandoc/core:3.5`), not from the haystack
container — that path never existed, since nothing under `docker/haystack/`
installs pypandoc. Pinning matters more here than elsewhere in the repo
because the output is committed; an unpinned converter means the committed
wikitext depends on whichever pandoc the last person had. Verified identical
across pandoc 3.5, 3.6.4, 3.7.0.2 and 3.10.

The post-processor lives in `scripts/lib/convert_docs_postprocess.py` and owns
the source→page mapping, the source list, and the wikitext cleanup — one
table, asked for by the shell script. It has golden files
(`tests/unit/test_convert_docs_postprocess.py`) that need neither docker nor
pandoc.

### Versions and upgrades

`VERSIONS.yml` at the repo root is the single answer to **"what version is
this?"**. Before it existed the tree gave four different answers at the same
time — `MW_VERSION` said 1.43.5, `app/composer.lock` said 5.1.4 with one
package at 5.1.5, `publiccode.yml` said 5.1.3, and the docs said 5.1.3 — so
"are we affected by CVE-X" could not be answered without reading the lockfile
by hand, and the openCode catalogue was being told the wrong number.

The `versions` check compares the declaration against the tree: `MW_VERSION`,
every `bluespice/*` version in `app/composer.lock`, all 184
`app/extensions/*/extension.json` files, `publiccode.yml`'s `softwareVersion`,
the image tags in `docker-compose.yml`, `docker/opensearch/Dockerfile`,
`docker/haystack/Dockerfile`, and the frozen-package strip list in
`docker/setup.sh`. It takes about a second, needs no containers and no network.

```bash
./scripts/check.sh --only versions          # the gate
python3 scripts/lib/versions.py scan        # what the tree says, as JSON
python3 scripts/lib/versions.py get bluespice
python3 scripts/lib/versions.py emit-extensions   # regenerate the inventory block
```

Editing rules live at the top of `VERSIONS.yml`. The two that matter: a
BlueSpice bump is a two-line edit (`bluespice:` plus `publiccode.yml`, because
all 58 BlueSpice extensions and all 59 `bluespice/*` packages are checked
against that one baseline), and the `extensions:` block is **generated**, never
hand-edited.

`frozen:` is Track C of the upstream-security process: `hallowelt/chatbot` and
`mediawiki/page-header` point at a private GitLab this project cannot reach, so
`docker/setup.sh` strips them from `composer.lock` and the vendored source is
used instead. No tooling will ever flag a CVE in them — the `last_reviewed`
date is the only signal they have. See `SECURITY.md`.

Note that setup.sh rewrites `app/composer.lock` **in place**, so those two
packages are in the lockfile on a pristine checkout and gone from it on any
installed tree. Both states are correct, and the gate asserts neither: what it
asserts is that both are still named in setup.sh's strip list, and that if they
*are* in the lockfile the version matches. Requiring presence made
`./scripts/check.sh` red after every install for a reason nobody could fix.

#### Known CVEs (`composer-audit`)

`composer audit --locked` is the only automated CVE signal for the
composer-visible packages. On this tree it reports **16 advisories across 4
packages, four of them high** — none of which is this fork's choice, since
`app/composer.json` is upstream's `bluespice/core` and every affected package
is a transitive dependency of MediaWiki 1.43.9 / BlueSpice 5.1.9.

It read 34 advisories across 12 packages, two critical, until the 1.43.9 /
5.1.9 upgrade cleared 28 of them. That is what the upgrade was for. The count
went 6 → 8 on 2026-08-04, when two new `guzzlehttp/guzzle` advisories
(CVE-2026-69246 host-check bypass, high; CVE-2026-69245 cookie-domain scope,
medium) were published against a version upstream pins exactly — the gate
caught them, which is the whole point of it. It went 8 → 16 on 2026-09-23
(DEPS-02), when `composer-audit` caught 9 new advisories against vendored
`mediawiki/semantic-media-wiki` 6.0.1, 8 of which entered the baseline (the
9th, `phpcsstandards/phpcsutils` CVE-2026-65954, is DEPS-01's, tracked
separately).

A bare audit as a gate would therefore be red on every push, and a permanently
red gate gets ignored. So the report is compared against
`docker/ci/composer-audit-baseline.json`, which records what is knowingly
carried **with a reason per package**, and the gate fails on anything *new* —
including a new advisory against a package already in the baseline.

```bash
./scripts/check.sh --only composer-audit        # the gate
scripts/ci/composer-audit.sh --report           # composer's own table
scripts/ci/composer-audit.sh --update-baseline  # re-record; reasons are kept
```

Four baseline entries used to be marked **ACTION REQUIRED**, all fixable only
by re-vendoring upstream. The 1.43.9 / 5.1.9 upgrade closed three of them:
`phpoffice/phpspreadsheet` 1.30.1 → 1.30.6 (was 2 critical, parses uploaded
spreadsheets), `phpseclib/phpseclib` 3.0.48 → 3.0.56 (was 2 high, sits under
the OIDC client), and `universal-omega/dynamic-page-list3` → 3.6.4 (leaked
suppressed usernames).

The fourth, `mediawiki/maps` (high, CVE-2026-52854, stored XSS via
`display_map`), **did not move and cannot**: the fix is in 12.1.3 and
`_bluespice/build/bluespice-pro-distribution/composer.json` constrains it to
`11.0.*`. No amount of re-vendoring inside the 5.1 series will clear it — only
a BlueSpice series bump that relaxes that constraint.

DEPS-02 (2026-09-23) added a second: `mediawiki/semantic-media-wiki`, 8
advisories against vendored 6.0.1 (two high — CVE-2025-61682 stored XSS and
`action=smwtask` unauthenticated access — plus six medium XSS/open-redirect),
fix floor 7.3.0. Blocked the same way, twice over: the same distribution file
constrains the package to `6.0.*`, and even past that, SMW 7.3.0's
`param-processor ~1.13` requirement has an empty intersection with
`bluespice/foundation`'s `1.12.*`. 7 of the 8 are mitigated in-tree by Class A
backport patches (`docs/dev/patches.md`) while the version itself stays
6.0.1 — the entry and the marker both stay for that reason. The 8th,
`action=smwtask`, is deliberately not backported here; it is carried by the
ChatBot Sibling Handler Sweep instead. Read the ACTION REQUIRED entries at the
start of every upgrade; they are the reason to take one.

This gate is blind to MediaWiki core (vendored source, not a composer package —
that is Track B, the release-watch job) and to the two frozen packages.

`renovate.json` is the other half of Track A: weekly, grouped one PR per
upstream, **never auto-merged** — merging is what triggers the patch
re-application. It is configured but unproven; no Renovate app is installed on
this repository yet.

#### Upstream releases (`release-watch`)

Track B, and the only thing in the repo that can notice a **MediaWiki core
security release**: core here is 53,938 committed files, not a composer
dependency, so no lockfile bump will ever mention it.

```bash
scripts/ci/release-watch.sh          # 0 up to date · 1 upstream moved · 77 a feed did not answer
scripts/ci/release-watch.sh --json
```

It compares `VERSIONS.yml` against `releases.wikimedia.org` (our branch, and
newer branches) and `packages.bluespice.com` (BlueSpice's own composer
repository, via `bluespice/foundation`). `.github/workflows/release-watch.yml`
runs it weekly and **files an issue**, not a PR — taking a MediaWiki release
here means re-vendoring the tree and re-applying 21 patches, which no bot can
prepare. Findings are `ACT` (a patch release on the series we run, where
security content lands) or `PLAN` (a newer series).

Two properties worth preserving, both tested in
`tests/unit/test_release_watch.py`: release candidates and `.tar.gz.sig` files
are **not** releases — the first false alarm is what teaches people to close
this issue unread — and a feed that fails to answer exits 77 and fails the job,
because silence must never read as "up to date".

#### Will the patches survive the upgrade? (`--upgrade-report`)

```bash
scripts/verify-patches.sh --upgrade-report --tree /path/to/candidate-upstream
```

The one tool between an upstream bump and silent patch loss. It classifies all
21 patches as GREEN / BLUE / AMBER / RED / N-A against a candidate tree — full
table and a worked example against the real MediaWiki 1.43.9 tarball in
[`patches.md`](patches.md#the-upgrade-report).

The trap worth stating twice: this asks the **opposite** question from
`verify-patches.sh`. There, a patch that applies cleanly means the patch is
missing from the tree. Here, a patch that applies cleanly is GREEN. Every state
is pinned by `tests/bats/upgrade_report.bats`.

**The human backstop is `mediawiki-announce`.** MediaWiki security releases are
announced there first and this job sees them up to a week later; subscribing
the maintainer address is a person's job and no workflow can do it. See
`SECURITY.md`.

### Shell behaviour (`bats`)

`tests/bats/` covers `docker/infisical-loader.sh` and nothing else.

`docker/setup.sh` is the other obvious candidate and is not testable at this
level — 557 lines, `set -euo pipefail`, `cd "$MW"` on line 18, and a
top-to-bottom installer body, so sourcing it in a test runs the installer.
Asserting its exit code means standing up MariaDB and composer first, which is
an integration test and belongs with Wave 3.

`infisical-loader.sh` is the opposite: designed to be sourced, with a clean
early-return path, and it carries two Wave 0 security fixes that regress
silently — the `:-` defaults that stop `set -u` aborting `setup.sh`, and the
client secret and bearer token travelling on stdin rather than argv.

`curl` is a test double that records its own `/proc/self/cmdline` — literally
what another process in the container can read, and unlike `ps aux` it cannot
miss the window in which the process is alive. `jq`, `bash` and the loader
itself are real.

**If you touch `infisical-loader.sh`, remember it is *sourced* under `set -euo
pipefail`.** A command substitution whose pipeline exits non-zero aborts
`setup.sh` itself, with the reason swallowed by the `2>/dev/null` that keeps
secrets out of the logs. That is why every `curl`/`jq` substitution in that file
ends in `|| true`; three separate crash paths (`061388d6e`) came from exactly
this.

---

## Conventions & Guardrails

### BlueSpice Extension Loading

BlueSpice loads extensions via `app/settings.d/*.php` processed in alphanumeric order — **NOT** via `wfLoadExtension()` calls in `LocalSettings.php`. The `setup.sh` script deliberately strips all auto-generated `wfLoadExtension` lines from `LocalSettings.php` and appends the `settings.d` loader.

### The `app/skins/.gitignore` Trap

`app/skins/.gitignore` starts with a blanket `/*` and then re-includes the six
skins this project ships:

```
/*
!/.gitignore
!/BlueSpiceDiscovery/   !/hdp/   !/MinervaNeue/
!/MonoBook/             !/Timeless/   !/Vector/
```

**Do not use `git add -f` for files under those six directories.** They are
already trackable, and `-f` only works for whoever remembers to type it — which
is exactly how the Vector `HookRunner.php` fix was lost in the first place
(`docs/QA-REPORT.md` Bug 2). If a file under a whitelisted skin appears
un-addable, that is a bug in the ignore rules; fix the rule.

Adding a **new** skin directory does need a matching `!/NewSkin/` line. Add the
line, do not reach for `-f`.

The same trap exists outside `app/skins/`, and it has bitten twice more:

- `app/composer.lock` and `app/composer.local.json` were tracked while still
  matched by upstream MediaWiki's ignore rules, so `git add -A` silently
  refused to re-add them. Both are load-bearing. Now negated explicitly.
- `app/skins/Vector/includes/FeatureManagement/FeatureManagerFactory.php` was
  simply never committed, which made `Special:Preferences` return HTTP 500 for
  every logged-in user until the vendored Vector skin was restored to upstream
  `REL1_43`.

`scripts/ci/patch-ignore-check.sh` now gates this for every patch target, and
`scripts/ci/fresh-clone.sh` gates it for every input `setup.sh` needs. Run
`./scripts/check.sh` and neither can regress silently.

To check a single path by hand, note that plain `git check-ignore` reports
nothing for a **tracked** file — the dangerous both-tracked-and-ignored state
looks clean. Use `--no-index`:

```bash
git check-ignore -v --no-index app/some/path.php
```

### Bind-Mount Behavior

`app/` and `docker/setup.sh` are bind-mounted into containers. Host edits are live — no rebuild needed for code/config changes. For PHP changes, the opcache may need clearing (FPM restart: `docker compose restart mediawiki`).

### Database: MariaDB Only

BlueSpice uses MySQL-specific SQL. SQLite will not work.

### Production-Grade Docker Only

This is a production-ready Docker Compose setup, not a dev playground. Changes should be made with production deployment in mind.

---

## Known Pitfalls

Each pitfall below has the **symptom** and the **fix** (root cause explained).

| Symptom | Fix | Root Cause |
|---------|-----|------------|
| Setup fails at MariaDB step / "credentials rejected" | `docker compose down -v` and start fresh | Stale `mariadb_data` volume from previous run with different `HDP_DB_PASSWORD` — volume is initialized once on first create |
| ChatBot fails with "The scheme '' is not supported" | Config already fixed in `app/settings.d/100-ChatBot.php` | ChatBot extension uses `$wg`-prefixed globals (`$wgBmbfDeepsetApiChatUrl`), not bare names |
| Error 1055 "isn't in GROUP BY" on every logged-in page load | Already fixed in `app/settings.d/050-Fixes.php` | `$wgSQLMode` re-adds `ONLY_FULL_GROUP_BY` — stripped in settings.d |
| `composer install` fails with "TypeError in Git::runCommand" or "cannot run ssh" | Already fixed in `docker/setup.sh` (SSH→HTTPS rewrite) | `composer.lock` pins packages to SSH URLs (`git@github.com:...`) — fresh containers have no SSH |
| Setup fails with "MariaDB not reachable after 60s" | Already increased to 120s wait in `docker/setup.sh` | Slow environments need longer wait for MariaDB to accept connections |
| "I changed .env but container still uses old LLM/embedding provider" | Check Infisical for stale `HDP_*` secrets | Infisical shadows `.env` — secrets with same names override at every container start |
| A file under a whitelisted skin appears un-addable | Fix the `.gitignore` rule; do **not** use `git add -f` | `app/skins/.gitignore` re-includes six skins by name — if one is un-addable the rule is wrong. Check with `git check-ignore -v --no-index <path>` |
| A patch you applied is gone after `setup.sh` | `bash scripts/apply-patches.sh --id <id>` | `composer install` reinstalls the package as a dist zipball over it. `scripts/verify-patches.sh` names the patch and the fix |
| Port 1417 already in use error | `docker compose down haystack && docker compose up -d haystack` | Old container process bound to port |
| Chatbot returns "no information found" for everything | Re-run ingestion with `--missing-only` | Ingestion was interrupted — OpenSearch index incomplete |

---

## Definition of Done

For this project, "verified" means:

1. **Fresh clone setup works** — `git clone` → `docker compose up -d` → `setup.sh` completes
2. **Wiki renders correctly** — Main page loads without errors
3. **Authenticated pages load** — `scripts/ci/t3-integration.sh` is green. In
   particular `Special:Preferences`, the page QA Bug 4 broke
4. **Ingestion succeeds** — OpenSearch `hdp_wiki` index has documents
   (baseline: **156 documents from 32 pages**, not ~828 — that figure was
   `bluespice_wikipage`, the ExtendedSearch index, which counts nested section
   documents)
5. **End-to-end chatbot answer with citation** — Query returns a correct answer with `[N]` source links

NOT just "it compiles" or "no PHP fatal errors."

---

## Do NOT

- **Do NOT touch running stacks** outside the current work directory — `/path/to/hdp-test`, `/path/to/hdp-pubtest`, etc. are isolated test stacks.
- **Do NOT commit secrets** — `.env` is gitignored for a reason; never commit actual passwords/API keys.
- **Do NOT break existing functionality** — This is a hard rule. The chatbot worked after QA fixes; changes must preserve that.
- **Do NOT use SQLite** — MariaDB is required for BlueSpice.
- **Do NOT modify `app/extensions/` directly** for site-specific config — use `app/settings.d/*.php` instead.
  - Carve-out: when upstream code genuinely has to change, the sanctioned path
    is a patch in the manifest (`docker/patches/`), applied by
    `scripts/apply-patches.sh` and gated by `scripts/verify-patches.sh`. See
    [`patches.md`](patches.md). **Do not add an inline `sed` to `setup.sh`** —
    that is what this replaced, and `sed -i` exits 0 when it matches nothing,
    so the patch silently vanishes on the next upstream reindent.
- **Do NOT duplicate the technical wiki** — Link to `docs/wiki/` instead of re-documenting architecture.
- **Do NOT hand-edit `docker/mediawiki/wiki-docs/*.wiki`** — they are generated
  from `docs/wiki/*.md` by `scripts/convert-docs.sh`, and the next regeneration
  discards the edit. Change the markdown (or the script, for
  `Help:Inhaltsverzeichnis`), regenerate, and commit both.
  `scripts/convert-docs.sh --check` catches drift either way.

---

## Quick Reference

### Services

| Service | Port | Purpose |
|---------|------|---------|
| mediawiki-web | 8080 | Wiki HTTP entry point |
| hayhooks | 1416 | Pipeline deploy/management UI |
| hdp_api_server (in haystack container) | 1417 | RAG query API |
| chatbot-proxy | 8080 (internal) | Deepset→Haystack adapter |

### Critical Files

| File | Purpose |
|------|---------|
| `docker-compose.yml` | All service definitions |
| `.env.example` | Environment template |
| `docker/setup.sh` | First-boot install (Composer, MediaWiki, BlueSpice tables) |
| `app/settings.d/050-Fixes.php` | MariaDB mode fixes |
| `app/settings.d/100-ChatBot.php` | ChatBot proxy config |
| `docker/haystack/ingest_hdp_wiki.py` | Ingestion script |
| `docker/haystack/hdp_pipeline.yaml` | RAG pipeline definition |
| `docs/wiki/` | Technical documentation (architecture, diagrams) |

### Commands

```bash
# Fresh start
docker compose down -v && docker compose up -d --build && docker compose exec mediawiki bash /setup.sh

# Ingest all wiki pages
docker compose exec haystack python3 ingest_hdp_wiki.py

# Test chatbot directly
docker compose exec haystack curl -s -X POST http://localhost:1417/hdp_pipeline/run \
  -H "Content-Type: application/json" \
  -d '{"question":"Your question","query":"Your question","path":"rag"}'

# Check OpenSearch index count
docker compose exec opensearch curl -sk -u "admin:${HDP_OPENSEARCH_PASSWORD}" \
  "https://localhost:9200/hdp_wiki/_count"

# Check service health
docker compose ps
```
