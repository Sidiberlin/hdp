# BlueSpice HDP — Technical Wiki

## What HDP Is

HDP ("Hallo Welt! Digitale Plattform" / BMBF-sponsored HDP Edition) is a fork
of [BlueSpice](https://en.wikipedia.org/wiki/BlueSpice) — a MediaWiki-based
enterprise wiki distribution — extended with a self-hosted **Retrieval-Augmented
Generation (RAG) chatbot**. Editors write and organize content as normal wiki
pages; a separate ingestion pipeline indexes that content into a vector
search store, and a chat widget embedded in the wiki UI lets readers ask
natural-language questions that are answered by an LLM grounded in — and
citing — the indexed wiki pages.

The whole stack (wiki + database + search + RAG pipeline) ships as a
Docker Compose deployment that a new user can bring up with `docker compose
up` plus one setup script — see [getting-started.md](getting-started.md).

## Key Concepts

- **BlueSpice / MediaWiki** (`app/`) — the wiki engine itself: MediaWiki core
  plus ~130 vendored BlueSpice extensions and skins, loaded via
  `app/settings.d/*.php` (see [settings-d](modules/settings-d.md)).
- **ChatBot extension** (`app/extensions/ChatBot/`) — the MediaWiki extension
  that renders the chat widget, exposes `/bmbf/*` REST endpoints, and proxies
  chat requests to the RAG backend. See
  [chatbot-extension](modules/chatbot-extension.md).
- **chatbot-proxy** (`docker/chatbot-proxy/`) — a small Python HTTP service
  that translates between the ChatBot extension's Deepset-Cloud-shaped API
  calls and the Haystack pipeline's own API, so the extension needs no code
  changes to talk to a self-hosted pipeline. See
  [docker-services](modules/docker-services.md).
- **Haystack RAG pipeline** (`docker/haystack/`) — a
  [Haystack](https://haystack.deepset.ai/) pipeline (query reformulation →
  hybrid BM25 + embedding retrieval → cross-encoder ranking → grounded LLM
  answer generation) served by `hayhooks`. See
  [haystack-pipeline](modules/haystack-pipeline.md).
- **Ingestion** (`docker/haystack/ingest_hdp_wiki.py`) — a standalone script
  that reads wiki pages straight from MediaWiki's API/database, splits them
  into sections, embeds them, and writes them into OpenSearch. This is the
  *only* thing that populates the chatbot's knowledge base in this
  deployment — see [ingestion](modules/ingestion.md).
- **Embedding providers** — the embedding step (used by both the live
  pipeline and ingestion) is swappable between a local CPU model, a remote
  OpenAI-compatible endpoint, or a one-off HuggingFace Space. See
  [embedding-providers](modules/embedding-providers.md).
- **OpenSearch** — stores wiki content as vector + BM25-searchable documents
  in the `hdp_wiki` index; also backs BlueSpice's own full-text search.
- **MariaDB** — the wiki's relational database (pages, revisions, users,
  BlueSpice extension tables).

## Entry Points

| Entry point | What happens |
|---|---|
| `docker compose up -d --build` | Builds/starts all 6 containers ([docker-compose.yml](../../docker-compose.yml)) |
| `docker compose exec mediawiki bash /setup.sh` | First-boot install: Composer fix, MediaWiki install, BlueSpice tables, main page ([docker/setup.sh](../../docker/setup.sh)) |
| `http://localhost:8080/w/` | The wiki itself (browser entry point) |
| `docker compose exec haystack python3 ingest_hdp_wiki.py` | Populates/refreshes the chatbot's search index from wiki content |
| `GET /bmbf/chat` (MediaWiki REST, called by the chat widget) | A user's chat question, streamed back as SSE |
| `POST http://localhost:1417/hdp_pipeline/run` | Direct HTTP entry point into the Haystack pipeline, bypassing the wiki UI |

## High-Level Architecture

A user's browser talks only to the wiki (Apache → PHP-FPM). The ChatBot
extension's REST endpoints proxy chat requests to `chatbot-proxy`, which
calls the Haystack pipeline; the pipeline retrieves from OpenSearch and
generates an answer with an OpenAI-compatible LLM, streamed back through the
same chain as Server-Sent Events. Wiki content reaches OpenSearch only via
the separate `ingest_hdp_wiki.py` script, run against the live MediaWiki API.
See [architecture.md](architecture.md) for the full diagram and design
decisions.

## Module Map

| Module | Doc | Covers |
|---|---|---|
| Docker services | [modules/docker-services.md](modules/docker-services.md) | `docker-compose.yml`, all 7 services, volumes, networking, health checks |
| ChatBot extension | [modules/chatbot-extension.md](modules/chatbot-extension.md) | `app/extensions/ChatBot/` — REST routes, `DeepsetApi` connector, chat UI, indexing hooks |
| Haystack pipeline | [modules/haystack-pipeline.md](modules/haystack-pipeline.md) | `docker/haystack/hdp_pipeline.yaml`, `hdp_api_server.py`, `entrypoint.sh` — the RAG query pipeline |
| Ingestion | [modules/ingestion.md](modules/ingestion.md) | `docker/haystack/ingest_hdp_wiki.py` — wiki → sections → embeddings → OpenSearch |
| Embedding providers | [modules/embedding-providers.md](modules/embedding-providers.md) | `local` / `remote` / `hf_space` embedding modes and the YAML-rendering trick that makes them swappable |
| settings.d | [modules/settings-d.md](modules/settings-d.md) | `app/settings.d/*.php` — BlueSpice's ordered extension/config loader, incl. the ChatBot + MariaDB fixes |

## Diagrams

- [architecture.md](architecture.md) — system-level `flowchart TD`
- [diagrams/sequences.md](diagrams/sequences.md) — chatbot query, ingestion, and first-boot setup as `sequenceDiagram`s
- [diagrams/class-diagram.md](diagrams/class-diagram.md) — why a class diagram isn't the right fit here, plus the small pieces of PHP class structure worth knowing

## Getting Started

See [getting-started.md](getting-started.md) for prerequisites, installation,
first run, and common workflows.
