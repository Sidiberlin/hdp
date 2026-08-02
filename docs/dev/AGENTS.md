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
| `docker/patches/` | Patch manifest — one YAML sidecar per patch, plus the Class-A `.patch` files | 19 entries; see [`patches.md`](patches.md) |
| `scripts/` | Contributor and CI entry points | `check.sh` (run before pushing), `verify-patches.sh`, `apply-patches.sh`, `ci/` |
| `tests/` | The test suite — see [The two pytest tiers](#the-two-pytest-tiers) | `unit/` (stdlib), `haystack/` (real haystack-ai), `bats/` (shell) |
| `docker/` | Docker build contexts and setup scripts | `setup.sh` (first-boot install), `haystack/` (RAG pipeline), `chatbot-proxy/` (Deepset→Haystack adapter) |
| `docker/haystack/` | Haystack RAG pipeline implementation | `hdp_pipeline.yaml`, `ingest_hdp_wiki.py`, `hdp_api_server.py`, `entrypoint.sh`, plus the two Wave 2 extractions `wikitext.py` (pure transforms) and `serialization.py` (`to_native`, `load_pipeline`) |
| `docs/` | User and architecture documentation | `QA-REPORT.md`, `embedding-providers.md`, `wiki/` (technical wiki) |
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
| `ruff` | Python under `docker/` and `tests/`, against the pinned ruleset in `ruff.toml` |
| `pytest-unit` | `tests/unit/` — stdlib-only unit tests, ~2s, no install needed |
| `pytest-haystack` | `tests/haystack/` — real `haystack-ai`, no mocks (see below) |
| `bats` | `docker/infisical-loader.sh` behaviour, incl. the two Wave 0 security fixes |
| `php-lint` | the 17 `app/settings.d/*.php` files gating ~130 extensions |
| `compose` | `docker compose config` on v2 |
| `gitleaks` | committed secrets, scoped to paths this project authors |
| `env-example` | every no-default `${VAR}` compose uses is in `.env.example` |
| `publiccode` | `publiccode.yml` schema — openCode validates this file |
| `patch-ignore` | no patch target is swallowed by a `.gitignore` rule |
| `manifest` | the patch manifest's schema and target paths |
| `fresh-clone` | ⭐ every input `setup.sh` needs is actually committed |
| `patches` | all 19 patches are in the tree (`--patches`) |

`fresh-clone` is the one worth understanding. `docs/QA-REPORT.md` records seven
bugs, six of them critical, and notes that each *"was invisible in the
development checkout … and only surfaced on a genuinely fresh clone."* It clones
the repo at HEAD into a throwaway directory and asserts the committed tree is
complete — which is the only way to catch a file that exists on your disk but
was never committed, or that a `.gitignore` rule silently refuses.

Note that it tests **committed** state. Uncommitted work in your tree is
deliberately not under test, and it says so when your tree is dirty.

### The two pytest tiers

Tests live in `tests/` at the repo root, not inside `docker/haystack/`. Those
directories are Docker build contexts; a test placed there either ships inside
the production image or needs `.dockerignore` surgery.

| Tier | Path | Needs | Cost |
|---|---|---|---|
| `pytest-unit` | `tests/unit/` | nothing but pytest | ~2s |
| `pytest-haystack` | `tests/haystack/` | `haystack-ai` + `envsubst` | 0s warm, ~47s cold |

The split follows what the code actually imports. `render_pipeline.render`,
`build_result_from_haystack` and the `wikitext` pure functions import nothing
outside the standard library, so they run on bare Python. Only `to_native()`
and `load_pipeline()` need Haystack.

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
scripts/ci/pytest.sh                  # both tiers
scripts/ci/pytest.sh --tier unit      # the fast one
scripts/ci/pytest.sh -- -k to_native  # args after -- go to pytest
```

Inside the running stack, the haystack image ships `pytest`, so:

```bash
docker compose run --rm --no-deps -v "$PWD/tests:/tests:ro" haystack \
    python -m pytest /tests/haystack
```

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
ends in `|| true`; three separate crash paths (`e33edc6c4`) came from exactly
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
3. **Ingestion succeeds** — OpenSearch `hdp_wiki` index has documents
4. **End-to-end chatbot answer with citation** — Query returns a correct answer with `[N]` source links

NOT just "it compiles" or "no PHP fatal errors."

---

## Do NOT

- **Do NOT touch running stacks** outside the current work directory — `/root/hdp-freshtest`, `/root/hdp-pubtest`, etc. are isolated test stacks.
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
