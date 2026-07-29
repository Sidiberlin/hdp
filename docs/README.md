# BlueSpice HDP Edition

**BlueSpice HDP** (Handbuch der Projektförderung) is an enterprise wiki platform combining MediaWiki 1.43 with the BlueSpice 4.5 Pro extension suite and an AI-powered RAG chatbot. It was developed collaboratively by the Bundesministerium für Bildung und Forschung (BMBF), deepset GmbH, GovTech Campus Deutschland, Hallo Welt! GmbH, and Fraunhofer IVV to make wiki content more accessible through natural-language question answering.

The system delivers answers grounded in curated wiki content — every response is backed by source documents with inline citations. A Haystack-based RAG pipeline provides hybrid retrieval (BM25 + embeddings), cross-encoder re-ranking, and Azure OpenAI (GPT-4o) answer generation.

## Key Concepts

- **BlueSpice Pro** — The commercial BlueSpice distribution (~130+ MediaWiki extensions), providing enterprise wiki features: permissions, workflows, content stabilization, semantic data, PDF export, and more.
- **HDP Edition** — A specialized build that adds a ChatBot extension and Haystack RAG pipeline to standard BlueSpice Pro, purpose-built for the "Handbuch der Projektförderung" knowledge base.
- **settings.d/ loading** — Extensions are enabled via numbered PHP files in `app/settings.d/`, loaded in ascending order. This controls the dependency graph and feature tiers (Free → Pro → Discovery → GovTech/ChatBot).
- **Haystack RAG Pipeline** — A hayhooks-deployed pipeline (`docker/haystack/hdp_pipeline.yaml`) that performs query reformulation, hybrid retrieval from OpenSearch, cross-encoder ranking, and LLM generation with six answer modes.
- **ChatBot Extension** — The MediaWiki-side bridge (`app/extensions/ChatBot/`) that provides the chat UI, REST API endpoints, Deepset API connector, and the indexing pipeline that syncs wiki content into OpenSearch.

## Entry Points

- [`docker-compose.yml`](../docker-compose.yml) — Docker Compose orchestration: MediaWiki (PHP-FPM) + Apache + JobRunner + OpenSearch + Haystack
- [`app/index.php`](../app/index.php) — MediaWiki web entry point
- [`app/LocalSettings.php`](../app/LocalSettings.php) — Main configuration; loads `LocalSettings.BlueSpice.php`
- [`app/LocalSettings.BlueSpice.php`](../app/LocalSettings.BlueSpice.php) — Auto-loads all `settings.d/*.php` files
- [`docker/haystack/hdp_pipeline.yaml`](../docker/haystack/hdp_pipeline.yaml) — Haystack RAG pipeline definition (deployed via hayhooks)
- [`pipeline/haystack-pipeline.py`](../pipeline/haystack-pipeline.py) — Python equivalent of the pipeline (reference/testing)

## High-Level Architecture

The system is a five-container Docker stack: a MediaWiki/BlueSpice PHP-FPM application, an Apache reverse proxy, a job runner, an OpenSearch cluster (for both wiki search and RAG vector store), and a Haystack/hayhooks container running the RAG pipeline. Wiki content is indexed into OpenSearch by the ChatBot extension; user questions flow from the browser chat UI through MediaWiki REST APIs to the Haystack pipeline, which retrieves relevant documents and generates grounded answers via Azure OpenAI.

See [architecture.md](architecture.md) for the full picture.

## Module Map

| Module | Purpose |
|---|---|
| [settings.d](modules/settings-d.md) | Extension loading order and BlueSpice tier configuration |
| [ChatBot Extension](modules/chatbot.md) | AI chatbot bridge: REST APIs, Deepset connector, indexing, UI |
| [Haystack RAG Pipeline](modules/haystack-pipeline.md) | Retrieval-augmented generation pipeline (YAML + Python) |
| [Docker Services](modules/docker-services.md) | Container topology and service configuration |
| [BlueSpice ExtendedSearch](modules/extended-search.md) | Search backend and external index integration |
| [BlueSpiceFoundation](modules/bluespice-foundation.md) | Core BlueSpice framework: services, hooks, config |

## Getting Started

See [getting-started.md](getting-started.md).
