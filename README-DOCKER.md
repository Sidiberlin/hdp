# BlueSpice HDP — Docker Setup

Clone-and-go Docker deployment of BlueSpice HDP Edition (MediaWiki/BlueSpice
wiki + Haystack RAG chatbot).

## Hardware Requirements

| Resource | Minimum | Recommended |
|----------|---------|-------------|
| **RAM** | 8 GB | 16 GB |
| **Disk** | 20 GB free | 30 GB free |
| **CPU** | 4 cores | 8 cores |
| **Docker** | 24.0+ | Latest |

Disk space breakdown: ~9 GB Docker images, ~2 GB MariaDB data, ~1.5 GB
embedding model (first-download), ~1 GB OpenSearch index, plus wiki uploads.
The 20 GB minimum covers that total (~13.5 GB before uploads) plus headroom
for Docker layer/log overhead and the ~5 GB of transient build-cache layers
a from-source build — the default path — needs on top of it.
The Docker image total breaks down as:

| Image | Approx. size |
|---|---|
| `hdp-haystack` | 2.57 GB |
| `hdp-opensearch` | 2.47 GB |
| `hdp-chatbot-proxy` | 0.18 GB |
| `mariadb` | 0.46 GB |
| `mediawiki` ×3 | ≈ 3.08 GB |

2.57 + 2.47 + 0.18 + 0.46 + 3.08 ≈ 8.8 ≈ 9 GB on a fresh pull —
measured 2026-08-17 against `v5.1.9` images.

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
it can generate for you. It writes `.env`, shows a summary, and then brings the
stack up and runs first-boot setup, so that what you have when it exits is a
wiki you can log into rather than a list of commands still to run.

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

Two questions after the configuration summary, and then it does the work itself:

1. **"Pre-download embedding models now?"** — asked only with local embeddings.
   The embedder and ranker (~1 GB) are fetched into the `haystack_models` volume
   now, where you can watch them, rather than behind haystack's healthcheck for
   the following five to ten minutes. Declining is fine: the container fetches
   them itself on first start, into the same volume, once.
2. **"Start the services now?"** — on yes, the installer pulls the published
   images, runs `docker compose up -d`, waits for MariaDB, OpenSearch and the
   wiki container to report healthy, and then runs `/setup.sh` itself — the
   several-minute first-boot install of MediaWiki and ~130 BlueSpice extensions.
   It closes by printing the wiki URL and the Admin login.

It does not ask whether to pull or build. It tries the pull and falls back to a
source build only if the registry cannot supply the images. On a GPU host the
pull uses `docker-compose.prod-gpu.yml`; see [GPU inference](#gpu-inference).

**One command is left to you.** Indexing the wiki for the chatbot takes 1–3
minutes per page on CPU, so it is yours to start when it suits:

```bash
docker compose exec haystack python3 ingest_hdp_wiki.py --dry-run   # preview
docker compose exec haystack python3 ingest_hdp_wiki.py             # for real
```

**Login:** `Admin` / (the `HDP_ADMIN_PASSWORD` the wizard generated or you set
in `.env` or Infisical). The installer prints it at the end.

Answer "no" to starting, or leave the Docker daemon stopped, and the installer
prints the two or three commands that reach the same place and exits 0. If
first-boot setup fails it leaves the containers up, prints the logs to look at
and the command to re-run, and exits 1 rather than claiming success.

<details>
<summary><strong>Manual setup — skip the wizard</strong></summary>

Nothing in the stack depends on having used `install.sh` — the only file it
writes is `.env`, and everything after that is the commands below. The
equivalent by hand:

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
#    ...on an NVIDIA host, the pre-built GPU images:
#    docker compose -f docker-compose.yml -f docker-compose.prod-gpu.yml up -d

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

## Updating an existing install

```bash
cd hdp
./update.sh
```

`update.sh` moves an already-configured, running install forward to the
**latest release tag** — the newest `v*` tag on the remote, release
candidates excluded. That is deliberately not "whatever is on `main` right
now": `main` is where every commit lands, including work in progress, and
the tagged releases are what this project considers shippable. Follow the
branch instead only if you know you want that:

```bash
HDP_UPDATE_REF=main ./update.sh       # opt-in: tip of main
HDP_UPDATE_REF=v5.2.0 ./update.sh     # pin one specific tag
./update.sh --check                   # show the plan, change nothing
```

What it does, in order: fetches the target, refuses if the working tree has
unexpected local changes, shows a plan (old → new commit, which of `.env`,
the images, and the wiki code will be touched, and the exact commands),
asks for confirmation, then — only after that — stops the two containers
serving live traffic, resets the tree, pulls or rebuilds the three images
this project builds, brings the stack back up, restarts the wiki for the
PHP opcache, and re-runs `docker/setup.sh` (which runs MediaWiki's
`update.php`). It offers a database backup first whenever `update.php` is
going to run, because a code rollback afterwards needs one — `update.sh`
itself never rolls back automatically; on failure it prints the exact
commands to do it by hand.

`.env`, the database and all three volumes are left alone except for one
thing: a new release can add `.env.example` keys, and `update.sh` offers to
append the ones with a documented default (keys with no safe default — API
keys, passwords — are listed instead, with a pointer to re-run
`./install.sh`, which pre-fills every answer from the existing `.env`).

Reachable the same three ways as the installer: from inside the checkout
(`./update.sh`), after the one-line install (`cd hdp && ./update.sh`), or
piped from curl the same way the installer is. It needs a real git checkout
— a tarball install has no history to fetch against.

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
every release `v*` tag (`-QoL*` prerelease tags are repo-level and
publish nothing) by [`.github/workflows/release.yml`](.github/workflows/release.yml):

| Image | Tag | Approx. size |
|---|---|---|
| `ghcr.io/sidiberlin/hdp-haystack` | `v5.1.9`, `latest` | 2.57 GB |
| `ghcr.io/sidiberlin/hdp-haystack` | `v5.1.9-gpu`, `latest-gpu` | ~8 GB |
| `ghcr.io/sidiberlin/hdp-opensearch` | `v5.1.9`, `latest` | 2.47 GB |
| `ghcr.io/sidiberlin/hdp-chatbot-proxy` | `v5.1.9`, `latest` | 177 MB |

`linux/amd64` only.

Haystack is published twice because PyTorch is chosen at build time: the `-gpu`
tag carries the CUDA wheels and the unsuffixed tag carries the CPU-only ones. No
runtime flag converts one into the other. The `-gpu` tag is a **CUDA 12.4**
build and needs a driver that supports 12.4 or newer; an older driver has to
build from source with `HAYSTACK_CUDA_VERSION=cu118` — see
[Driver compatibility](#driver-compatibility-haystack_cuda_version).
OpenSearch and the chatbot proxy have
no model in them and are published once each — the GPU deployment uses exactly
the same two images. See [GPU inference](#gpu-inference).

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
working tree. On an NVIDIA host use `docker-compose.prod-gpu.yml` in its place —
same idea, `-gpu` haystack image, device reserved.

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

`docker-compose.prod.yml` and `docker-compose.prod-gpu.yml` default to the
release they ship with, so a fresh clone gets a known-good set with no
configuration. To move, set the tag in `.env`:

```bash
HDP_IMAGE_TAG=v5.1.9
```

Both files read the same variable, and the GPU one appends its suffix after it —
`HDP_IMAGE_TAG=v5.1.9` there means `hdp-haystack:v5.1.9-gpu`.

`:latest` exists but deliberately does not follow a pre-release tag — `v5.1.9`
moves it, `v5.1.9-rc1` does not. Pin explicitly for anything you care about.

#### Why `git describe` and `docker images` disagree

On a full clone checked out at a `-QoL*` tag the two commands disagree:

```bash
git describe --tags    # v5.1.9-QoL2[-N-gHASH] on a full clone at the tag
docker images          # the hdp-* images are still tagged v5.1.9
```

Both outputs are correct. `-QoL*` tags are repository-level — docs, fixes,
workflow changes — and publish no images at all: the release workflow skips
them, so the images you pull track the base release they were built from
(`v5.1.9`, measured 2026-08-17). To pin a release explicitly, set
`HDP_IMAGE_TAG` as described above. For a third check — the OCI revision
label naming the commit an image was built from — see
[Verifying what you pulled](#verifying-what-you-pulled).

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

## GPU inference

Embedding a wiki page on CPU takes 1–3 minutes; on an NVIDIA GPU it takes
seconds. `install.sh` detects a GPU and offers to use it. By hand there are two
paths, and they use **different override files** — pick one, do not combine them.

**Pull the pre-built GPU image.** Since `v5.1.9` the release workflow publishes a
CUDA build of the haystack image under a `-gpu` tag, so a GPU host no longer has
to build ~8 GB from source:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod-gpu.yml pull
docker compose -f docker-compose.yml -f docker-compose.prod-gpu.yml up -d
```

`docker-compose.prod-gpu.yml` is `docker-compose.prod.yml` and
`docker-compose.gpu.yml` merged into one file: it pulls
`ghcr.io/sidiberlin/hdp-haystack:${HDP_IMAGE_TAG:-v5.1.9}-gpu` plus the two
device-agnostic images, and reserves the host GPU for `haystack`. It replaces
both — adding either of them to that command is wrong.

**Build from source.** Set `HAYSTACK_DEVICE=gpu` in `.env` and add the build
override:

```bash
docker compose -f docker-compose.yml -f docker-compose.gpu.yml up -d --build
```

That override reserves the host GPU for the `haystack` service and pins
`HAYSTACK_DEVICE=gpu` for both the build and the container, so the image is
built with CUDA PyTorch (~8 GB, against ~2 GB for the CPU image).

### Driver compatibility (`HAYSTACK_CUDA_VERSION`)

NVIDIA drivers are backward compatible but not forward compatible: a newer
driver runs an older CUDA runtime, never the other way round. The published
`-gpu` image and the default source build both carry **CUDA 12.4** PyTorch, so
they need a driver that supports CUDA 12.4 or newer. On an older one the
container builds, starts, passes its healthcheck, and then fails on the first
embedding with:

```
CUDA driver version is insufficient for CUDA runtime version
```

`nvidia-smi` prints the ceiling in its header — `CUDA Version: 12.4` means "the
highest CUDA this driver supports", not "the toolkit installed here":

| Driver supports  | `HAYSTACK_CUDA_VERSION` | How to get it                    |
| ---------------- | ----------------------- | -------------------------------- |
| CUDA 12.4+       | `cu124` (default)       | pre-built `-gpu` image, or source |
| CUDA 11.8 – 12.3 | `cu118`                 | source build only                |
| below CUDA 11.8  | —                       | no GPU build works; use CPU or update the driver |

A CUDA 12.0 driver takes `cu118`, not `cu124`: `cu118` wheels run on every
driver from 11.8 up, 12.x included. There is no pre-built `cu118` image, so that
host must build from source:

```bash
# in .env
HAYSTACK_DEVICE=gpu
HAYSTACK_CUDA_VERSION=cu118

docker compose -f docker-compose.yml -f docker-compose.gpu.yml up -d --build
```

`install.sh` does all of this for you: it reads the ceiling out of `nvidia-smi`,
writes the matching `HAYSTACK_CUDA_VERSION`, skips the pre-built image when it
would not run, and falls back to CPU embeddings on a driver older than 11.8.
Changing the variable later means a rebuild — the wheels are chosen at build
time, not at start-up.

Either way the host needs the **NVIDIA Container Toolkit** — a working driver is
not enough, since that says nothing about whether containers can reach the GPU:

```bash
sudo apt-get install -y nvidia-container-toolkit
sudo nvidia-ctk runtime configure --runtime=docker
sudo systemctl restart docker
# verify (use an image tag your driver supports — 11.8.0-base-ubuntu22.04 on a
# driver whose ceiling is below CUDA 12.4, or this check fails for a reason that
# has nothing to do with the toolkit):
docker run --rm --gpus all nvidia/cuda:12.4.0-base-ubuntu22.04 nvidia-smi
```

Without it, `up` fails with `could not select device driver "nvidia" with
capabilities: [[gpu]]`.

Do **not** stack `docker-compose.gpu.yml` on `docker-compose.prod.yml`. That
combination parses and then fails at `up`: `prod.yml`'s `build: !reset null`
drops the inherited `dockerfile:` path, the GPU override's build args re-create a
`build:` key without one, and compose then tries to build a non-existent
`./Dockerfile`. `docker-compose.prod-gpu.yml` exists precisely so that "GPU
without building" is one file rather than that combination — it sets no build
args at all, because the device is already baked into the `-gpu` image.

`install.sh` picks between the two for you: choose GPU inference and it tries
`prod-gpu.yml` first, falling back to `gpu.yml` and a source build only if the
`-gpu` image cannot be pulled — or, on a driver that needs `cu118`, without
trying the pull at all.

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
- **Inference device**: `HAYSTACK_DEVICE` is `cpu` by default. On a machine
  with an NVIDIA GPU, see [GPU inference](#gpu-inference) above.

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

**Yellow banner about missing "Site:Nutzungsbedingungen" / "Site:Datenschutz" / "Site:Impressum" / "Site:Haftungsausschluss" / "Site:Über" pages:** After a fresh install, minimal placeholder legal pages (Terms of Use, Privacy Policy, Imprint, Disclaimer, and About) are created automatically by `setup.sh`. The wiki admin should customize `Site:Nutzungsbedingungen`, `Site:Datenschutz`, `Site:Impressum`, `Site:Haftungsausschluss`, and `Site:Über` with appropriate legal content for their organization.

**SyntaxHighlight code blocks render as plain `<pre>` (no syntax coloring):** If you still see this after a fresh `setup.sh` run, python3/Pygments may not have installed correctly. Check the setup output for the "Installing python3 + pygments" line. You can install manually: `docker compose exec mediawiki apt-get install -y python3 python3-pygments`.

**Wiki feels sluggish right after setup:** The first-boot setup creates ~350+ pages and queues hundreds of indexing/link-update jobs. The single `mediawiki-jobrunner` container processes these serially — give it 5–10 minutes after `setup.sh` completes before expecting search, categories, and link tables to be fully consistent.

## Notes

- The `app/` directory contains the full BlueSpice MediaWiki source (core + ~130 extensions), vendored and tracked in git per upstream's distribution model. Do not modify files under `app/extensions/` directly for site-specific config — use `app/settings.d/*.php` instead (loaded automatically, see `setup.sh`).
- `LocalSettings.php` (the base file, before the BlueSpice settings.d loader is appended) and `app/vendor/` are generated/modified by `setup.sh`.
- MariaDB is required — SQLite will not work with BlueSpice extensions.
- License: GPLv3 (see `LICENSE`). This is a fork of the BMBF-sponsored [BlueSpice HDP Edition](https://gitlab.opencode.de/bmbf/teamdigital/hdp) — see that repo for the original project and Hallo Welt! GmbH's copyright notice.
