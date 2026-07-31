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
| `app/skins/` | MediaWiki skins | **WARNING:** `.gitignore` contains `/*` — use `git add -f` to commit skin files |
| `docker/` | Docker build contexts and setup scripts | `setup.sh` (first-boot install), `haystack/` (RAG pipeline), `chatbot-proxy/` (Deepset→Haystack adapter) |
| `docker/haystack/` | Haystack RAG pipeline implementation | `hdp_pipeline.yaml`, `ingest_hdp_wiki.py`, `hdp_api_server.py`, `entrypoint.sh` |
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

## Conventions & Guardrails

### BlueSpice Extension Loading

BlueSpice loads extensions via `app/settings.d/*.php` processed in alphanumeric order — **NOT** via `wfLoadExtension()` calls in `LocalSettings.php`. The `setup.sh` script deliberately strips all auto-generated `wfLoadExtension` lines from `LocalSettings.php` and appends the `settings.d` loader.

### The `app/skins/.gitignore` Trap

`app/skins/.gitignore` contains a blanket `/*` exclusion. To commit skin files (e.g., the `HookRunner.php` fix for Vector), use `git add -f`:

```bash
git add -f app/skins/Vector/includes/Hooks/HookRunner.php
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
| `app/skins/Vector/includes/Hooks/HookRunner.php` changes vanish | Use `git add -f` | `app/skins/.gitignore` has `/*` blanket exclusion |
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
