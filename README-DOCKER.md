# BlueSpice HDP — Docker Setup

Clone-and-go Docker deployment of BlueSpice HDP Edition (MediaWiki/BlueSpice
wiki + Haystack RAG chatbot).

## Hardware Requirements

| Resource | Minimum | Recommended |
|----------|---------|-------------|
| **RAM** | 8 GB | 16 GB |
| **Disk** | 15 GB free | 30 GB free |
| **CPU** | 4 cores | 8 cores |
| **Docker** | 24.0+ | Latest |

Disk space breakdown: ~4 GB Docker images, ~2 GB MariaDB data, ~1.5 GB
embedding model (first-download), ~1 GB OpenSearch index, plus wiki uploads.
CPU-only embedding ingestion takes 1–3 min per wiki page; a GPU or the
`hf_space` embedding provider (see
[`docs/embedding-providers.md`](docs/embedding-providers.md)) is significantly
faster for large wikis.

## Quick Start

One command on a fresh Linux box, from any directory:

```bash
curl -fsSL https://raw.githubusercontent.com/Sidiberlin/hdp/main/install.sh | bash
```

`install.sh` clones this repository into `./hdp`, checks that Docker, Compose
v2 and OpenSSL are present, and then walks you through every configuration
choice: secrets backend (plain `.env` or Infisical), site name/language/port,
LLM provider and API key, embedding provider, and the four passwords — which
it can generate for you. It writes `.env`, shows a summary, and offers to
start the stack.

Already cloned, or prefer to read a script before running it? Same wizard:

```bash
git clone https://github.com/Sidiberlin/hdp.git
cd hdp
./install.sh
```

It is safe to re-run: an existing `.env` is copied to `.env.bak` first and its
values become the defaults for every question, so re-running is how you change
one setting without retyping the rest.

### What happens after the wizard

The wizard offers to start the stack — either pulling the pre-built images
(`docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d`, a
download) or building from source (`docker compose up -d --build`, ~5 minutes).
It then prints these steps, which are still yours to run:

```bash
# 1. Wait for MariaDB and OpenSearch to become healthy (30-60s)
docker compose ps

# 2. First-boot setup: installs MediaWiki + ~130 BlueSpice extensions.
#    Several minutes, once per installation.
docker compose exec mediawiki bash /setup.sh

# 3. Open the wiki and log in
open http://localhost:8080/w/

# 4. Index the wiki for the chatbot — it answers nothing until this runs
docker compose exec haystack python3 ingest_hdp_wiki.py --dry-run   # preview
docker compose exec haystack python3 ingest_hdp_wiki.py             # for real
```

**Login:** `Admin` / (the `HDP_ADMIN_PASSWORD` the wizard generated or you set
in `.env` or Infisical)

<details>
<summary><strong>Manual setup — skip the wizard</strong></summary>

`install.sh` only writes `.env`; nothing in the stack depends on having used
it. The equivalent by hand:

```bash
# 1. Configure environment
cp .env.example .env
# Edit .env — at minimum set the four *_PASSWORD variables and HDP_LLM_API_KEY
# (or configure Infisical for secret management — see .env.example).
# HDP_OPENSEARCH_PASSWORD must pass a zxcvbn strength check; generate one with:
#   printf 'Hdp-%s-26!\n' "$(openssl rand -hex 4)"

# 2. Build and start all services
docker compose up -d --build
#    ...or pull the pre-built images instead of building:
#    docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# 3. Wait for MariaDB and OpenSearch to be healthy (30-60s)
docker compose ps

# 4. Run first-boot setup (installs MediaWiki + ~130 BlueSpice extensions)
docker compose exec mediawiki bash /setup.sh

# 5. Open the wiki
open http://localhost:8080/w/
```

</details>

**Building from source is the default and always works.** The pre-built-image
override is a convenience, not a requirement, and nothing else in this
repository depends on it. See [Pre-built images](#pre-built-images) for pinning
and how the images are produced. The other four services (MariaDB and the three
Wikimedia PHP-FPM/Apache/jobrunner images) are upstream images and are pulled
either way.

> **Note — private by default:** BlueSpice requires login before any page
> (including the main page) is visible to anonymous visitors. Log in with
> the Admin account above. On first login you'll be asked to accept a
> privacy consent — this is standard BlueSpice behavior.

### First ingestion — indexing wiki content for the chatbot

The RAG chatbot needs wiki pages indexed into OpenSearch before it can
answer questions. This is a separate step after `setup.sh`:

```bash
# Preview what will be indexed (no writes)
docker compose exec haystack python3 ingest_hdp_wiki.py --dry-run

# Run the full ingestion
docker compose exec haystack python3 ingest_hdp_wiki.py
```

**Timing:** With the default `local` embedding provider (CPU), expect
**1–3 minutes per wiki page**. A fresh install with ~30 pages takes
roughly 30–60 minutes; a large wiki can take hours. The embedding model
(~1.4 GB) is downloaded on first run. For faster bulk ingestion, see
[`docs/embedding-providers.md`](docs/embedding-providers.md) for the
`remote` (GPU server) or `hf_space` (HuggingFace ZeroGPU) options.

Ingestion is idempotent — re-run it any time content changes:

```bash
docker compose exec haystack python3 ingest_hdp_wiki.py --missing-only
```

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
| `opensearch` | Custom build (`docker/opensearch/`, on `opensearchproject/opensearch:2.18.0`) | Search + RAG vector store |
| `haystack` | Custom build (`docker/haystack/`) | Haystack RAG pipeline (hayhooks + custom FastAPI wrapper on port 1417) |
| `chatbot-proxy` | Custom build (`docker/chatbot-proxy/`) | Bridges BlueSpice ChatBot extension's Deepset-API format to Haystack's API |

Ports published to the host: `${MW_DOCKER_PORT:-8080}` (wiki),
`${HAYHOOKS_PORT:-1416}` (hayhooks admin/docs UI), `${HDP_PDF_PORT:-1417}`
(Haystack RAG query API, used by `chatbot-proxy` and directly testable).

The last three rows are the three images this project builds; the first four
are upstream images used unchanged. Those three are also published — see
below.

## Pre-built images

The three custom images are published to the GitHub Container Registry on
every `v*` tag by [`.github/workflows/release.yml`](.github/workflows/release.yml):

| Image | Approx. size |
|---|---|
| `ghcr.io/sidiberlin/hdp-haystack` | 2.57 GB |
| `ghcr.io/sidiberlin/hdp-opensearch` | 2.47 GB |
| `ghcr.io/sidiberlin/hdp-chatbot-proxy` | 177 MB |

`linux/amd64` only.

### Two supported paths, and which to use

**Build from source — the default.** `docker compose up -d --build`, as in the
Quick Start. This is what CI runs, what every test tier runs, and the only
path that picks up local changes. If you are developing on this repository,
this is your path and the override below is not for you.

**Pull the published images.** Add the override file:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

This is for operators deploying a release unchanged. It trades the ~5 minute
build for a download, and it pins you to a published tag rather than to your
working tree.

**Requires Docker Compose ≥ 2.24.** Check with `docker compose version`.
`docker-compose.yml` declares `build:` for these three services, and a service
with both `build:` and `image:` is *built* rather than pulled whenever the image
is not already local — which is exactly the five-minute build this path exists
to avoid, with no error to explain it. The override deletes the inherited key
with the `!reset` tag, which 2.24 introduced.

On an older compose this file **fails to parse** rather than silently ignoring
the tag, so you will see an error — it just will not mention the version. The
fallback there is to pull explicitly before bringing the stack up:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml pull
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

### Pinning a version

`docker-compose.prod.yml` defaults to the release it ships with, so a fresh
clone gets a known-good set with no configuration. To move, set the tag in
`.env`:

```bash
HDP_IMAGE_TAG=v5.1.9
```

`:latest` exists but deliberately does not follow a pre-release tag — `v5.1.9`
moves it, `v5.1.9-rc1` does not. Pin explicitly for anything you care about.

### Authentication

None. The packages are public — `docker compose ... pull` and `up` work with
no `docker login` and no token.

### Verifying what you pulled

Images published by the workflow carry OCI labels naming the commit they were
built from:

```bash
docker image inspect ghcr.io/sidiberlin/hdp-haystack:v5.1.9 \
  --format '{{index .Config.Labels "org.opencontainers.image.revision"}}'
```

Every tag published from `v5.1.9` onward goes through the workflow and carries
that label.

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
zero extra config) — expect anywhere from a few seconds to a few minutes
per page depending on page size and host CPU. For a large wiki, see
[`docs/embedding-providers.md`](docs/embedding-providers.md) for the
`remote` (dedicated GPU server) or `hf_space` (one-off ZeroGPU) options,
both significantly faster.

Verify the index after ingestion:

```bash
docker compose exec opensearch bash -c \
  'curl -sk -u "admin:$OPENSEARCH_INITIAL_ADMIN_PASSWORD" https://localhost:9200/hdp_wiki/_count'
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
# Remove the generated LocalSettings.php so setup.sh re-runs a fresh install
rm -f app/LocalSettings.php

# Stop everything and delete all data volumes (wiki content, search index, DB)
docker compose down -v

# Start fresh
docker compose up -d --build
docker compose exec mediawiki bash /setup.sh
docker compose exec haystack python3 ingest_hdp_wiki.py
```

> **Note:** `app/cache/` debug logs are unrotated and can grow large on
> long-lived instances. Periodically clear or prune this directory if disk
> space is tight.

## Troubleshooting

**`curl` returns 502/503 on the wiki:** FPM may still be starting. Wait 10s and retry.

**Setup fails at MariaDB step:** Check `docker compose logs mariadb`. Ensure `HDP_DB_PASSWORD` in `.env` matches what MariaDB was initialized with (if you already have a `mariadb_data` volume from a previous run with different credentials, `docker compose down -v` and start fresh).

**Page loads but missing styling:** Run `docker compose exec mediawiki php maintenance/run.php update.php --quick`.

**ChatBot UI doesn't appear / stays hidden:** Check the browser console for a ResourceLoader error (`Failed to get load.php URL`). Historically this was caused by a missing `HookRunner.php` under `app/skins/Vector/includes/Hooks/`, and this section used to tell you to hand-write a stub for it.

That advice was wrong about the cause and is no longer needed. `HookRunner.php` is **not** a gap in the upstream Vector package — upstream `REL1_43` ships it, along with the `VectorSearchResourceLoaderConfigHook` interface it implements. The file was missing because the copy of Vector vendored into this repo was incomplete: 59 files under `includes/` had never been committed. They have since been restored from upstream `REL1_43`, so no stub is required on a fresh clone. If you still see this error, run `git status app/skins/Vector` — the fault is a local modification, not a missing upstream file.

**Chatbot returns "no information found" for everything:** Almost always incomplete ingestion, not a pipeline bug. Compare `hdp_wiki` document count (see "Running / Re-running Ingestion" above) against your wiki's actual page count — if ingestion was interrupted partway, run `--missing-only` to finish it.

**LLM calls time out / retry repeatedly in `haystack` logs:** Reasoning-heavy models (e.g. GLM's chain-of-thought) can take 1-2 minutes to answer a RAG prompt with retrieved documents in context. Both `OpenAIGenerator` components in `hdp_pipeline.yaml` are set to `timeout: 300` — if you're still seeing timeouts with a different/slower model, increase this value. Faster models (e.g. `gpt-4o`) typically respond in 60-110s for a full RAG query.

**"I changed `.env` but the container is still using the old LLM/embedding provider":** If you use Infisical, `infisical-loader.sh` fetches every secret whose name starts with `HDP_` — if you have stale `HDP_LLM_BASE_URL`/`HDP_LLM_MODEL`/etc. stored as Infisical secrets from an earlier config, they silently override `.env` on every container start. Check your Infisical project for stale `HDP_*` entries, or use `.env`-only config (don't create Infisical secrets with the same names as your non-secret config vars).

**PHP Notice `"Spezialseiten" alias for special page 'EnhancedSpecialPages' conflicts with page from Specialpages` during setup:** Harmless and expected. The `EnhancedStandardUIs` extension registers the German alias `Spezialseiten` for its `EnhancedSpecialPages` page, which collides with MediaWiki core's own `Special:Spezialseiten` alias — MediaWiki logs a Notice and keeps the core mapping. This is an upstream issue in `app/extensions/EnhancedStandardUIs/languages/EnhancedSpecialPages.i18n.alias.php` (the extension ships the conflicting alias). No fix applied here to avoid divergence from vendored upstream; ignore the notice.

**Port 1417 already in use:** An old `haystack` container process may still be bound to it. `docker compose down haystack && docker compose up -d haystack`.

**A container crashed/was OOM-killed and didn't come back on its own:** All services set `restart: unless-stopped`, which should auto-restart a crashed container. On some restricted Docker hosts (nested/sandboxed Docker daemons, some CI environments, some managed VPS providers) this restart supervision doesn't actually fire even though the policy is set correctly — verify with `docker inspect <container> --format '{{.RestartCount}}'` after a crash. If it's stuck at 0 and the container stays `Exited`, that's this host limitation, not a config bug; run `docker compose up -d` to bring it back manually, and consider an external supervisor (systemd unit wrapping `docker compose up`, a cron healthcheck, or a proper non-nested Docker host) for unattended production use.

**Yellow banner about missing "Site:Nutzungsbedingungen" / "Site:Datenschutz" pages:** After a fresh install, minimal placeholder legal pages (Terms of Use / Privacy Policy) are created automatically by `setup.sh`. The wiki admin should customize `Site:Nutzungsbedingungen` and `Site:Datenschutz` with appropriate legal content for their organization.

**SyntaxHighlight code blocks render as plain `<pre>` (no syntax coloring):** If you still see this after a fresh `setup.sh` run, python3/Pygments may not have installed correctly. Check the setup output for the "Installing python3 + pygments" line. You can install manually: `docker compose exec mediawiki apt-get install -y python3 python3-pygments`.

**Wiki feels sluggish right after setup:** The first-boot setup creates ~350+ pages and queues hundreds of indexing/link-update jobs. The single `mediawiki-jobrunner` container processes these serially — give it 5–10 minutes after `setup.sh` completes before expecting search, categories, and link tables to be fully consistent.

## Notes

- The `app/` directory contains the full BlueSpice MediaWiki source (core + ~130 extensions), vendored and tracked in git per upstream's distribution model. Do not modify files under `app/extensions/` directly for site-specific config — use `app/settings.d/*.php` instead (loaded automatically, see `setup.sh`).
- `LocalSettings.php` (the base file, before the BlueSpice settings.d loader is appended) and `app/vendor/` are generated/modified by `setup.sh`.
- MariaDB is required — SQLite will not work with BlueSpice extensions.
- License: GPLv3 (see `LICENSE`). This is a fork of the BMBF-sponsored [BlueSpice HDP Edition](https://gitlab.opencode.de/bmbf/teamdigital/hdp) — see that repo for the original project and Hallo Welt! GmbH's copyright notice.
