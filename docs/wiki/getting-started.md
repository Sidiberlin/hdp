# Getting Started

## Prerequisites

- Docker + Docker Compose v2 (`docker compose`, not the legacy `docker-compose`)
- Enough host RAM for the default memory limits: MariaDB 1g, MediaWiki 1g,
  MediaWiki-web 512m, jobrunner 512m, OpenSearch 1536m, Haystack 3g,
  chatbot-proxy 256m — roughly **8GB** total headroom is a safe minimum
  (all overridable via `HDP_*_MEM_LIMIT` in `.env`, see
  [docker-compose.yml](../../docker-compose.yml))
- An API key for an OpenAI-compatible chat completions endpoint (OpenAI,
  z.ai/GLM, Nebius, etc.) for the LLM
- Optionally, an [Infisical](https://infisical.com/) project, if you want
  centrally-managed secrets instead of a plaintext `.env`

## Installation

```bash
git clone <this repo>
cd hdp

# 1. Configure environment
cp .env.example .env
# Edit .env: at minimum set HDP_DB_ROOT_PASSWORD, HDP_DB_PASSWORD,
# HDP_ADMIN_PASSWORD, HDP_OPENSEARCH_PASSWORD, and HDP_LLM_API_KEY
# (or configure the INFISICAL_* block instead — see .env.example header)

# 2. Build and start all services
docker compose up -d --build

# 3. Wait for MariaDB and OpenSearch to report healthy
docker compose ps

# 4. Run first-boot setup (installs MediaWiki + ~130 BlueSpice extensions)
docker compose exec mediawiki bash /setup.sh

# 5. Open the wiki
open http://localhost:8080/w/
```

Login as `Admin` with the `HDP_ADMIN_PASSWORD` you set. See
[README-DOCKER.md](../../README-DOCKER.md) for the full reference,
including reset/reinstall and troubleshooting.

## What `setup.sh` Actually Does

[`docker/setup.sh`](../../docker/setup.sh) runs inside the `mediawiki`
container and is idempotent (safe to re-run):

1. **Waits for MariaDB** to accept connections (up to 120s).
2. **Fixes Composer**: two packages (`hallowelt/chatbot`,
   `mediawiki/page-header`) are pinned in `composer.lock` to a private
   GitLab and to SSH-form GitHub URLs; their full source is already
   committed under `app/extensions/`, so the script rewrites SSH→HTTPS,
   strips those two packages from the lockfile, and reinstalls.
3. **Installs MediaWiki** via `maintenance/install.php` against MariaDB,
   then strips the installer's auto-generated `wfLoadExtension()` calls
   from `LocalSettings.php` and appends
   `require_once "$IP/LocalSettings.BlueSpice.php";` instead — this hands
   extension loading over to [settings.d](modules/settings-d.md), which
   loads BlueSpice's ~130 extensions in a specific order with the right
   config (skipped entirely on re-run if `LocalSettings.php` already
   exists).
4. **Runs `update.php`** to create all BlueSpice extension tables (run
   twice — once before, once after fixing directory permissions, since some
   extensions like SemanticMediaWiki need writable data dirs that don't
   exist until the first pass creates them).
5. **Populates the main page** from
   [`docker/mediawiki/hauptseite.wiki`](../../docker/mediawiki/hauptseite.wiki)
   — once only, guarded by a `cache/.hauptseite-populated` marker so later
   admin edits are never overwritten.

## First Run: Create Content and Ask the Chatbot

1. Create a few wiki pages (through the UI, or `maintenance/edit.php` for
   bulk/scripted content).
2. Index them into the chatbot's search backend:
   ```bash
   docker compose exec haystack python3 ingest_hdp_wiki.py
   ```
3. Verify indexing:
   ```bash
   docker compose exec opensearch curl -sk -u "admin:${HDP_OPENSEARCH_PASSWORD}" \
     "https://localhost:9200/hdp_wiki/_count"
   ```
4. Open the wiki, log in, and use the chat widget (bottom-right chat icon)
   to ask a question about the content you just indexed. Expect roughly
   60-110s for a `gpt-4o`-class model, longer for reasoning-heavy models —
   see the Troubleshooting section of
   [README-DOCKER.md](../../README-DOCKER.md).

## Common Workflows

### Create page → ingest → ask chatbot

The core loop. Indexing is **not** automatic on save (see
[Key Design Decisions](architecture.md#key-design-decisions)) — re-run
ingestion after content changes:

```bash
# Reindex everything
docker compose exec haystack python3 ingest_hdp_wiki.py

# Or just the pages not yet indexed (fast resume after a partial run)
docker compose exec haystack python3 ingest_hdp_wiki.py --missing-only

# Or a single page you just edited
docker compose exec haystack python3 ingest_hdp_wiki.py --page "Hauptseite"
```

See [ingestion](modules/ingestion.md) for what the script does internally.

### Query the RAG pipeline directly (bypassing the wiki UI)

Useful when debugging retrieval/generation issues in isolation from the
PHP/proxy layer:

```bash
docker compose exec haystack curl -s -X POST http://localhost:1417/hdp_pipeline/run \
  -H "Content-Type: application/json" \
  -d '{"question":"Your question here","query":"Your question here","path":"rag"}'
```

### Switch embedding provider

Edit `HDP_EMBEDDING_PROVIDER` (and its related `HDP_EMBEDDING_*` vars) in
`.env`, then:

```bash
docker compose up -d --force-recreate haystack
```

See [embedding-providers](modules/embedding-providers.md) for the three
modes and their tradeoffs.

## Configuration

All runtime configuration is in `.env` (start from `.env.example`). The
variables that matter most day-to-day:

| Variable | Purpose |
|---|---|
| `HDP_DB_ROOT_PASSWORD`, `HDP_DB_PASSWORD` | MariaDB root / `bluespice` app user passwords |
| `HDP_ADMIN_PASSWORD` | Wiki `Admin` account password |
| `HDP_OPENSEARCH_PASSWORD` | OpenSearch `admin` password (needed even with Infisical, since Compose creates the container before any secret-loader runs) |
| `HDP_LLM_BASE_URL`, `HDP_LLM_MODEL`, `HDP_LLM_API_KEY` | The OpenAI-compatible chat completions endpoint used for query reformulation + answer generation |
| `HDP_EMBEDDING_PROVIDER`, `HDP_EMBEDDING_MODEL`, `HDP_EMBEDDING_DIM` | Which embedder the live pipeline and ingestion use — see [embedding-providers](modules/embedding-providers.md) |
| `MW_DOCKER_PORT`, `HAYHOOKS_PORT`, `HDP_PDF_PORT` | Host ports for the wiki, hayhooks admin API, and the Haystack query API respectively |
| `INFISICAL_URL` / `INFISICAL_PROJECT_ID` / `INFISICAL_CLIENT_ID` / `INFISICAL_CLIENT_SECRET` | Optional: pulls every `HDP_`-prefixed secret from Infisical at container start, overriding `.env` |

⚠️ If you use Infisical: it fetches **every** secret whose name starts with
`HDP_`, not just credentials — a stale `HDP_LLM_BASE_URL` or
`HDP_EMBEDDING_MODEL` left over in Infisical from an earlier config will
silently win over your `.env` edit on every container start. Either keep
non-secret config out of Infisical, or keep both in sync.

Application-level (as opposed to deployment-level) configuration lives in
[`app/settings.d/*.php`](../../app/settings.d/), loaded in filename order by
`app/LocalSettings.BlueSpice.php` — most relevantly
[`050-Fixes.php`](../../app/settings.d/050-Fixes.php) (MariaDB SQL-mode fix)
and [`100-ChatBot.php`](../../app/settings.d/100-ChatBot.php) (points the
ChatBot extension at `chatbot-proxy`). See
[settings-d](modules/settings-d.md).
