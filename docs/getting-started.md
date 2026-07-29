# Getting Started

## Prerequisites

- **Docker** 24+ and **Docker Compose** v2
- **Azure OpenAI** resource with GPT-4o deployment (for AI chatbot)
- **8 GB RAM** minimum (OpenSearch + Haystack + MediaWiki)
- **Git** for cloning the repository

## Installation

```bash
# Clone the repository
git clone https://gitlab.opencode.de/bmbf/teamdigital/hdp.git
cd hdp

# Copy environment template and fill in your values
cp .env.example .env
nano .env  # Set AZURE_OPENAI_ENDPOINT, AZURE_OPENAI_API_KEY, AZURE_OPENAI_DEPLOYMENT

# Start all services
docker compose up -d

# Install Composer dependencies (PHP deps for MediaWiki/BlueSpice)
docker compose exec mediawiki composer update

# Run the MediaWiki installer + BlueSpice setup
docker compose exec mediawiki /bin/bash /docker/install.sh
```

## First Run

```bash
# Check all services are running
docker compose ps

# Open the wiki in your browser
open http://localhost:8080
```

You should see the BlueSpice Discovery skin with the HDP wiki. The chatbot widget appears in the bottom-right corner. Ask a question to test the RAG pipeline.

**Admin login:** `Admin` / value of `MEDIAWIKI_PASSWORD` (default: `dockerpass`)

## Common Workflows

### View Haystack Pipeline Status

```bash
# Check if hayhooks is running and pipeline is deployed
curl http://localhost:1416/docs

# List deployed pipelines
curl http://localhost:1416/pipelines
```

### Rebuild the Search Index

```bash
# Rebuild BlueSpice ExtendedSearch index
docker compose exec mediawiki php maintenance/run.php extensions/BlueSpiceExtendedSearch/maintenance/rebuildIndex.php

# Purge and re-index ChatBot's OpenSearch index
docker compose exec mediawiki php maintenance/run.php extensions/ChatBot/maintenance/purgeIndex.php
```

### Check OpenSearch

```bash
# Cluster health
curl -sk -u admin:Hdp-Search-2024! https://localhost:9200/_cluster/health

# List indices
curl -sk -u admin:Hdp-Search-2024! https://localhost:9200/_cat/indices?v
```

### View Logs

```bash
# MediaWiki logs (in container)
docker compose exec mediawiki cat cache/*.log

# Haystack pipeline logs
docker compose logs haystack

# OpenSearch logs
docker compose logs opensearch
```

### Rebuild Haystack Image (after pipeline changes)

```bash
docker compose build haystack
docker compose up -d haystack
```

## Configuration

Key config files and settings:

- **`.env`** — All secrets and environment-specific settings (LLM API keys, DB passwords, ports)
  - `AZURE_OPENAI_ENDPOINT` — Azure OpenAI resource URL (required for chatbot)
  - `AZURE_OPENAI_API_KEY` — Azure OpenAI API key (required)
  - `AZURE_OPENAI_DEPLOYMENT` — GPT-4o deployment name (default: `gpt-4o`)
  - `MW_DOCKER_PORT` — HTTP port for wiki (default: 8080)
  - `HAYHOOKS_PORT` — hayhooks API port (default: 1416)
  - `HAYSTACK_DEVICE` — `cpu` (default) or `gpu` (requires NVIDIA runtime)
- **`app/LocalSettings.php`** — MediaWiki base config; loads BlueSpice settings
- **`app/LocalSettings.BlueSpice.php`** — Auto-loader for `settings.d/*.php` (the extension loading mechanism)
- **`app/settings.d/`** — Numbered PHP files enabling extensions in tier order
- **`docker/haystack/hdp_pipeline.yaml`** — RAG pipeline definition (components, connections, prompts)
- **`docker-compose.yml`** — Service definitions for all five containers
- **ChatBot config (MediaWiki)** — Set via `LocalSettings.local.php` or Special:ConfigManager:
  - `$wgBmbfDeepsetApiChatUrl` — hayhooks pipeline URL
  - `$wgBmbfDeepsetApiKey` — Deepset API key for indexing/feedback
  - `$wgBmbfDeepsetApiIndexUrl` — Deepset indexing API URL

## GPU Mode (optional)

For faster embeddings on NVIDIA hardware:

```bash
# Set in .env
HAYSTACK_DEVICE=gpu

# Rebuild and start with GPU support
docker compose build haystack
docker compose up -d haystack
```

Requires NVIDIA Container Toolkit installed on the host.

## Where to Go Next

- Architecture overview: [architecture.md](architecture.md)
- Module reference: [README.md#module-map](README.md#module-map)
- Diagrams: [diagrams/](diagrams/)
