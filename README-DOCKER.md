# BlueSpice HDP — Docker Setup

Clone-and-go Docker deployment of BlueSpice HDP Edition (MediaWiki/BlueSpice
wiki + Haystack RAG chatbot).

## Quick Start

```bash
# 1. Configure environment
cp .env.example .env
# Edit .env — at minimum set the *_PASSWORD variables and HDP_LLM_API_KEY
# (or configure Infisical for secret management — see .env.example)

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

**Login:** `Admin` / (the `HDP_ADMIN_PASSWORD` you set in `.env` or Infisical)

### Accessing from other machines

By default the wiki is configured for `localhost` access only. If you want it
reachable from other devices in your LAN, set `MW_SERVER` in `.env` to the
host's IP or hostname **before** running setup.sh:

```bash
# In .env — replace with your server's actual IP/hostname
MW_SERVER=http://192.168.1.50:8080
```

Without this, logins and redirects send browsers to their own `localhost`
and fail with `ERR_CONNECTION_REFUSED`.

## Architecture

| Service | Image / Build | Purpose |
|---------|-------|---------|
| `mariadb` | `mariadb:10.11` | Database (BlueSpice uses MySQL-specific SQL) |
| `mediawiki` | `docker-registry.wikimedia.org/dev/bookworm-php83-fpm:1.0.0` | PHP-FPM application server |
| `mediawiki-web` | `docker-registry.wikimedia.org/dev/bookworm-apache2:1.0.1` | Apache reverse proxy |
| `mediawiki-jobrunner` | `docker-registry.wikimedia.org/dev/bookworm-php83-jobrunner:1.0.0` | Background job runner |
| `opensearch` | `opensearchproject/opensearch:2.18.0` | Search + RAG vector store |
| `haystack` | Custom build (`docker/haystack/`) | Haystack RAG pipeline (hayhooks + custom FastAPI wrapper on port 1417) |
| `chatbot-proxy` | Custom build (`docker/chatbot-proxy/`) | Bridges BlueSpice ChatBot extension's Deepset-API format to Haystack's API |

Ports published to the host: `${MW_DOCKER_PORT:-8080}` (wiki),
`${HAYHOOKS_PORT:-1416}` (hayhooks admin/docs UI), `${HDP_PDF_PORT:-1417}`
(Haystack RAG query API, used by `chatbot-proxy` and directly testable).

## What `setup.sh` Does

The setup script handles first-boot installation:

1. **Composer fix** — Two packages (`hallowelt/chatbot`, `mediawiki/page-header`) reference a private GitLab. Their full source is already committed in `app/extensions/`, so the script strips them from `composer.lock`, reinstalls dependencies, and regenerates the autoloader.
2. **MediaWiki install** — Runs `install.php` with MariaDB credentials, creating `LocalSettings.php`.
3. **BlueSpice settings loader** — Appends the `settings.d/*.php` loader to `LocalSettings.php`, which activates ~130 BlueSpice extensions in the correct order with their configuration (including `settings.d/100-ChatBot.php`, which points the ChatBot extension at the `chatbot-proxy` service — no manual `$wgBmbfDeepsetApiChatUrl` configuration needed).
4. **Database migration** — Runs `update.php` to create all BlueSpice extension tables.

## Configuration

All configuration is in `.env` — see `.env.example` for the full, commented
reference (secrets, LLM provider, embedding provider, wiki settings). Two
things worth calling out here:

- **Secrets**: this project supports both Infisical (recommended for
  teams/production) and plain `.env` fallback (for local dev). See the
  header comment in `.env.example`.
- **Embedding provider**: the wiki-content embedder is configurable across
  three modes (local CPU, remote OpenAI-compatible API, or a HuggingFace
  ZeroGPU Space for fast one-off ingestion). See
  [`docs/embedding-providers.md`](docs/embedding-providers.md).

## Running / Re-running Ingestion

The RAG chatbot needs wiki content indexed into OpenSearch before it can
answer questions. Ingestion is idempotent — safe to re-run any time content
changes:

```bash
# Full reindex of all wiki pages
docker compose exec haystack python3 ingest_hdp_wiki.py

# Only index pages not already in OpenSearch (fast resume after a partial run)
docker compose exec haystack python3 ingest_hdp_wiki.py --missing-only

# Single page (useful after editing one page)
docker compose exec haystack python3 ingest_hdp_wiki.py --page "Hauptseite"

# Preview without writing
docker compose exec haystack python3 ingest_hdp_wiki.py --dry-run
```

By default this uses the `local` embedding provider (CPU, in-container,
zero extra config) — expect roughly 1-3 minutes per page depending on host
CPU. For a large wiki, see
[`docs/embedding-providers.md`](docs/embedding-providers.md) for the
`remote` (dedicated GPU server) or `hf_space` (one-off ZeroGPU) options,
both significantly faster.

Verify the index after ingestion:

```bash
docker compose exec opensearch curl -sk -u "admin:${HDP_OPENSEARCH_PASSWORD}" \
  "https://localhost:9200/hdp_wiki/_count"
```

## Testing the RAG Pipeline Directly

Bypass the wiki UI and query the Haystack pipeline directly (useful for
debugging retrieval/generation issues in isolation):

```bash
docker compose exec haystack curl -s -X POST http://localhost:1417/hdp_pipeline/run \
  -H "Content-Type: application/json" \
  -d '{"question":"Your question here","query":"Your question here","path":"rag"}'
```

Expect a 1-4 minute response time depending on the LLM's reasoning
verbosity (see Troubleshooting below) — this hits the full pipeline:
query reformulation → hybrid retrieval → cross-encoder ranking → grounded
answer generation.

## Reset / Reinstall

```bash
# Stop everything and delete all data volumes (wiki content, search index, DB)
docker compose down -v

# Start fresh
docker compose up -d --build
docker compose exec mediawiki bash /setup.sh
docker compose exec haystack python3 ingest_hdp_wiki.py
```

## Troubleshooting

**`curl` returns 502/503 on the wiki:** FPM may still be starting. Wait 10s and retry.

**Setup fails at MariaDB step:** Check `docker compose logs mariadb`. Ensure `HDP_DB_PASSWORD` in `.env` matches what MariaDB was initialized with (if you already have a `mariadb_data` volume from a previous run with different credentials, `docker compose down -v` and start fresh).

**Page loads but missing styling:** Run `docker compose exec mediawiki php maintenance/run.php update.php --quick`.

**ChatBot UI doesn't appear / stays hidden:** Check the browser console for a ResourceLoader error (`Failed to get load.php URL`). This is caused by a missing `HookRunner.php` in the Vector skin — a pre-existing gap in the upstream `Vector` skin package, not something this repo's setup causes. If you hit it on a fresh clone, create a stub at `app/skins/Vector/includes/Hooks/HookRunner.php`:
```php
<?php
namespace MediaWiki\Skins\Vector\Hooks;
class HookRunner {
    public function onVectorSearchResourceLoaderConfig( &$config ) { return true; }
}
```

**Chatbot returns "no information found" for everything:** Almost always incomplete ingestion, not a pipeline bug. Compare `hdp_wiki` document count (see "Running / Re-running Ingestion" above) against your wiki's actual page count — if ingestion was interrupted partway, run `--missing-only` to finish it.

**LLM calls time out / retry repeatedly in `haystack` logs:** Reasoning-heavy models (e.g. GLM's chain-of-thought) can take 1-2 minutes to answer a RAG prompt with retrieved documents in context. Both `OpenAIGenerator` components in `hdp_pipeline.yaml` are set to `timeout: 300` — if you're still seeing timeouts with a different/slower model, increase this value. Faster models (e.g. `gpt-4o`) typically respond in 60-110s for a full RAG query.

**"I changed `.env` but the container is still using the old LLM/embedding provider":** If you use Infisical, `infisical-loader.sh` fetches every secret whose name starts with `HDP_` — if you have stale `HDP_LLM_BASE_URL`/`HDP_LLM_MODEL`/etc. stored as Infisical secrets from an earlier config, they silently override `.env` on every container start. Check your Infisical project for stale `HDP_*` entries, or use `.env`-only config (don't create Infisical secrets with the same names as your non-secret config vars).

**Port 1417 already in use:** An old `haystack` container process may still be bound to it. `docker compose down haystack && docker compose up -d haystack`.

**A container crashed/was OOM-killed and didn't come back on its own:** All services set `restart: unless-stopped`, which should auto-restart a crashed container. On some restricted Docker hosts (nested/sandboxed Docker daemons, some CI environments, some managed VPS providers) this restart supervision doesn't actually fire even though the policy is set correctly — verify with `docker inspect <container> --format '{{.RestartCount}}'` after a crash. If it's stuck at 0 and the container stays `Exited`, that's this host limitation, not a config bug; run `docker compose up -d` to bring it back manually, and consider an external supervisor (systemd unit wrapping `docker compose up`, a cron healthcheck, or a proper non-nested Docker host) for unattended production use.

## Notes

- The `app/` directory contains the full BlueSpice MediaWiki source (core + ~130 extensions), vendored and tracked in git per upstream's distribution model. Do not modify files under `app/extensions/` directly for site-specific config — use `app/settings.d/*.php` instead (loaded automatically, see `setup.sh`).
- `LocalSettings.php` (the base file, before the BlueSpice settings.d loader is appended) and `app/vendor/` are generated/modified by `setup.sh`.
- MariaDB is required — SQLite will not work with BlueSpice extensions.
- License: GPLv3 (see `LICENSE`). This is a fork of the BMBF-sponsored [BlueSpice HDP Edition](https://gitlab.opencode.de/bmbf/teamdigital/hdp) — see that repo for the original project and Hallo Welt! GmbH's copyright notice.
