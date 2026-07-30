# CLAUDE.md — Claude Code Quick Start for HDP

This is the Claude Code-specific entry point for the HDP repository. For full project documentation, see **[AGENTS.md](AGENTS.md)**.

---

## Quick Orientation

**HDP** is a Dockerized MediaWiki/BlueSpice wiki with a self-hosted RAG chatbot. The stack runs in 7 containers: MariaDB, MediaWiki (PHP-FPM + Apache + jobrunner), OpenSearch, Haystack RAG pipeline, and a chatbot proxy.

**Architecture docs:** [`docs/wiki/`](docs/wiki/) — system diagrams, data flow, and design decisions.

**Operating manual:** [`AGENTS.md`](AGENTS.md) — repo map, environment setup, verification steps, conventions, known pitfalls.

---

## First-Time Setup

```bash
# 1. Configure environment
cp .env.example .env
# Edit .env — set *_PASSWORD and HDP_LLM_API_KEY at minimum

# 2. Start stack
docker compose up -d --build

# 3. Run first-boot setup
docker compose exec mediawiki bash /setup.sh

# 4. Open wiki
open http://localhost:8080/w/
# Login: Admin / (HDP_ADMIN_PASSWORD from .env)
```

---

## Verification Checklist

After setup, verify with:

```bash
# All services healthy
docker compose ps

# Ingest wiki content into OpenSearch
docker compose exec haystack python3 ingest_hdp_wiki.py

# Check index populated
docker compose exec opensearch curl -sk -u "admin:${HDP_OPENSEARCH_PASSWORD}" \
  "https://localhost:9200/hdp_wiki/_count"

# Test chatbot directly (bypasses wiki UI)
docker compose exec haystack curl -s -X POST http://localhost:1417/hdp_pipeline/run \
  -H "Content-Type: application/json" \
  -d '{"question":"What is this wiki about?","query":"What is this wiki about?","path":"rag"}'
```

---

## Claude Code Specifics

### Permission Model

- Commands that modify files (Edit, Write) require permission approval
- Read operations are generally safe
- Docker commands (`docker compose *`) require explicit approval each time

### Root Operations

- Running as `root` means `--dangerously-skip-permissions` is unnecessary for most operations
- But Docker commands still prompt individually

### Key Files to Read Before Editing

| File | Why |
|------|-----|
| `AGENTS.md` | Full operating manual |
| `docker-compose.yml` | All service definitions |
| `app/settings.d/100-ChatBot.php` | ChatBot proxy config (uses `$wg` prefix) |
| `app/settings.d/050-Fixes.php` | MariaDB mode fixes |
| `docker/setup.sh` | First-boot installation |
| `docker/haystack/ingest_hdp_wiki.py` | Wiki → OpenSearch ingestion |

### Known Gotchas for Claude Code

1. **`app/skins/.gitignore` has `/*`** — To commit skin files, use `git add -f app/skins/...`
2. **Bind mounts are live** — Editing files under `app/` affects running containers immediately (no rebuild)
3. **Infisical shadows `.env`** — If using Infisical, `HDP_*` secrets override `.env` at every container start
4. **MariaDB volume persistence** — If `HDP_DB_PASSWORD` changes, run `docker compose down -v` to reset

---

## Definition of Done

For HDP, "done" means:

1. Fresh clone → `docker compose up` → all containers healthy
2. `setup.sh` completes without errors
3. Wiki pages render correctly
4. Ingestion populates OpenSearch (`hdp_wiki` index has documents)
5. Chatbot returns answers **with citations** — e.g., `[3]` links

NOT just "no fatal errors."

---

## Common Commands

```bash
# Restart with rebuild
docker compose up -d --build

# Re-run setup
docker compose exec mediawiki bash /setup.sh

# Re-ingest wiki content
docker compose exec haystack python3 ingest_hdp_wiki.py

# Check service status
docker compose ps

# Clean restart (wipes all data)
docker compose down -v && docker compose up -d --build && docker compose exec mediawiki bash /setup.sh
```

---

## Full Documentation

- **[AGENTS.md](AGENTS.md)** — Complete operating manual (repo map, conventions, pitfalls)
- **[README-DOCKER.md](README-DOCKER.md)** — Quick start and troubleshooting
- **[docs/QA-REPORT.md](docs/QA-REPORT.md)** — QA verification results and known limitations
- **[docs/wiki/](docs/wiki/)** — Technical wiki with architecture diagrams
