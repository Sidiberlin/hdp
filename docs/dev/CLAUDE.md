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
docker compose exec opensearch bash -c \
  'curl -sk -u "admin:$OPENSEARCH_INITIAL_ADMIN_PASSWORD" https://localhost:9200/hdp_wiki/_count'

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
| `docker/haystack/wikitext.py` | Pure wiki-text transforms — has unit tests, keep them passing |
| `docker/haystack/serialization.py` | `to_native` + `load_pipeline` — has unit tests |
| `tests/` | The suite; see AGENTS.md "The two pytest tiers" |

### Testing

```bash
scripts/ci/pytest.sh --tier unit   # stdlib only, ~2s — run on every save
scripts/ci/pytest.sh               # both tiers (adds real haystack-ai)
scripts/ci/bats.sh                 # shell behaviour
./scripts/check.sh                 # everything CI runs, ~100s

# Inside the running stack (the haystack image ships pytest).
# --entrypoint python is required: entrypoint.sh never exec "$@", so without it
# hayhooks boots instead of pytest and dies on the read-only /w mount.
# Mount the repo ROOT — pytest.ini lives there and one test walks up for
# docker-compose.yml; mounting only tests/ gives 39 passed and 1 error.
docker compose run --rm --no-deps --entrypoint python \
    -v "$PWD:/w:ro" -w /w haystack \
    -m pytest tests/unit tests/haystack -p no:cacheprovider
```

Nothing in the suite is mocked. See AGENTS.md for why that is affordable and
for the golden-file regeneration workflow.

### Known Gotchas for Claude Code

1. **`app/skins/.gitignore` has `/*`** — Six shipped skins are whitelisted (BlueSpiceDiscovery, hdp, MinervaNeue, MonoBook, Timeless, Vector). To add a new skin dir, add a matching `!/NewSkin/` line or use `git add -f`.
2. **Bind mounts are live** — Editing files under `app/` affects running containers immediately (no rebuild)
3. **Infisical shadows `.env`** — If using Infisical, `HDP_*` secrets override `.env` at every container start
4. **MariaDB volume persistence** — If `HDP_DB_PASSWORD` changes, run `docker compose down -v` to reset
5. **`docker/infisical-loader.sh` is *sourced* under `set -euo pipefail`** — a command substitution that exits non-zero aborts `setup.sh` itself, with the reason swallowed by the `2>/dev/null` that keeps secrets out of the logs. That is why every `curl`/`jq` substitution there ends in `|| true`. Three crash paths came from exactly this (`e33edc6c4`); `tests/bats/` guards them now.
6. **Tests live at the repo root, not in the build contexts** — `docker/haystack/` and `docker/chatbot-proxy/` are Docker build contexts, so a test placed there ships in the production image

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
