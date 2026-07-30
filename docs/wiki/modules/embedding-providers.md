# Module: Embedding Providers

The embedder used both by the live RAG query pipeline's `query_embedder`
component and by [`ingest_hdp_wiki.py`](ingestion.md)'s document embedding
step is configurable across three modes via `HDP_EMBEDDING_PROVIDER` in
`.env`. This module explains each mode, when to use it, and how the
type-level swap works under the hood.

## Responsibilities

- Provide a single configuration knob (`HDP_EMBEDDING_PROVIDER`) that
  changes how both ingestion and live queries turn text into vectors.
- Keep the choice consistent: whatever embedded the documents at ingestion
  time must produce vectors in the same space the live query embedder
  produces, or retrieval quality silently degrades.

## Key Files

- [`docker/haystack/render_pipeline.py`](../../../docker/haystack/render_pipeline.py) — swaps the live pipeline's `query_embedder` component type (`local` vs `remote`) at container start
- [`docker/haystack/hdp_pipeline.yaml`](../../../docker/haystack/hdp_pipeline.yaml) — contains the `HDP_EMBEDDER_BLOCK_START`/`_END` sentinel comments `render_pipeline.py` rewrites between
- [`docker/haystack/ingest_hdp_wiki.py`](../../../docker/haystack/ingest_hdp_wiki.py) — `LocalEmbedder` / `RemoteEmbedder` / `HFSpaceEmbedder` classes + `make_embedder()` factory
- [`docker/haystack/entrypoint.sh`](../../../docker/haystack/entrypoint.sh) — validates `HDP_EMBEDDING_PROVIDER` at container start and pre-downloads the local model if needed
- [`.env.example`](../../../.env.example) — the full `HDP_EMBEDDING_*` variable reference

## Public API / Interfaces

| Mode | `HDP_EMBEDDING_PROVIDER` | Valid for live pipeline? | Valid for ingestion? |
|---|---|---|---|
| Local (default) | `local` | Yes | Yes |
| Remote | `remote` | Yes | Yes |
| HF Space | `hf_space` | **No** — `render_pipeline.py` raises `SystemExit` if set | Yes (`--provider hf_space`) |

### Mode: `local` (default)

Runs [sentence-transformers](https://www.sbert.net/) in-container, CPU by
default (GPU if built with `HAYSTACK_DEVICE=gpu`). Zero extra config beyond
the default `HDP_EMBEDDING_MODEL`/`HDP_EMBEDDING_DIM`. This is what
upstream BlueSpice HDP ships with.

```bash
HDP_EMBEDDING_PROVIDER=local
HDP_EMBEDDING_MODEL=mixedbread-ai/deepset-mxbai-embed-de-large-v1   # default
HDP_EMBEDDING_DIM=1024                                              # default
```

Tradeoff: bulk ingestion is slow on constrained hosts — each page's chunks
are embedded synchronously, one page at a time, so a large wiki can take
hours on CPU-only hardware. Query-time embedding (one short question) is
fast regardless of hardware, so this only matters for the initial bulk
ingestion run, not for live chatbot responsiveness.

### Mode: `remote`

Points the embedder at any OpenAI-compatible embeddings API — a self-hosted
[Text-Embeddings-Inference](https://github.com/huggingface/text-embeddings-inference)
server, or a commercial embeddings API.

```bash
HDP_EMBEDDING_PROVIDER=remote
HDP_EMBEDDING_BASE_URL=https://api.openai.com/v1     # or your TEI server URL
HDP_EMBEDDING_MODEL=text-embedding-3-small
HDP_EMBEDDING_DIM=1536                               # must match the model's real output dim
HDP_EMBEDDING_API_KEY=                                # blank if the endpoint needs no auth
```

`HDP_EMBEDDING_DIM` is baked into the OpenSearch `hdp_wiki` index mapping
(`embedding_dim`) — changing it after the index already has documents from
a different-dimension model requires a full re-ingest into a fresh index,
since old and new vectors aren't comparable.

### Mode: `hf_space` (ingestion/testing only)

Embeds via a HuggingFace [ZeroGPU](https://huggingface.co/docs/hub/spaces-zerogpu)
Gradio Space through `gradio_client`. Useful for a one-off fast bulk
re-index without provisioning a dedicated GPU host.

```bash
# NOT valid as HDP_EMBEDDING_PROVIDER for the live pipeline — pass via --provider instead:
docker compose exec haystack python3 ingest_hdp_wiki.py --provider hf_space
```

requires `HDP_EMBEDDING_HF_SPACE_ID` (e.g.
`your-username/your-embedder-space`) and optionally
`HDP_EMBEDDING_HF_TOKEN`/`HF_TOKEN`. `HFSpaceEmbedder.__init__()` runs a
smoke-test call (`["ping"]`) so a broken Space fails fast, before touching
MariaDB or the wiki. `HFSpaceEmbedder._call()` retries up to 5 times with
increasing backoff (`(attempt+1)*15`s) on quota-exceeded errors.

## Why `hf_space` Is Ingestion-Only

`render_pipeline.py`'s `BLOCKS` dict only defines `local` and `remote` — if
`HDP_EMBEDDING_PROVIDER=hf_space` is passed to the live pipeline, the
`haystack` container's entrypoint validation step
(`entrypoint.sh`'s `case "$HDP_EMBEDDING_PROVIDER" in ... *) ... exit 1`)
refuses to start, with the same reasoning `render_pipeline.py`'s docstring
gives: HF Spaces cold-start (10-60s on an idle Space) and enforce
per-account/session usage quotas — both unacceptable for a live, 24/7 chat
query path, but tolerable for a bounded, supervised, occasional batch job.

## How the Swap Works (Implementation Notes)

Haystack pipeline YAML declares component **types** statically —
`SentenceTransformersTextEmbedder` (local) and `OpenAITextEmbedder`
(remote) are different Python classes with different required
`init_parameters`, so plain `${VAR}` substitution via `envsubst` (which can
only swap parameter *values*) can't switch between them.

`render_pipeline.py` solves this with a text-replace: it finds the block
between the `HDP_EMBEDDER_BLOCK_START`/`_END` sentinel comments in
`hdp_pipeline.yaml` and replaces it wholesale with the correct
component-type block for the configured provider — **before** `envsubst`
and hayhooks/`hdp_api_server.py` ever parse the file. This runs once, at
container start (`entrypoint.sh`), so switching `HDP_EMBEDDING_PROVIDER`
only requires `docker compose up -d --force-recreate haystack`, not a
manual YAML edit.

`ingest_hdp_wiki.py` uses a different mechanism for the same problem — a
small Python class hierarchy (`LocalEmbedder`/`RemoteEmbedder`/
`HFSpaceEmbedder`, selected by `make_embedder()`) — since it's plain Python
and has no YAML-vs-class-instantiation constraint to work around.

## Dependencies

- **Uses:** `sentence-transformers` (local), an OpenAI-compatible HTTP API
  (remote), `gradio_client` + a user-deployed HF Space (hf_space)
- **Used by:** [haystack-pipeline](haystack-pipeline.md)'s `query_embedder`
  component; [ingestion](ingestion.md)'s document embedding step

## Notable Patterns / Gotchas

- **Infisical shadowing applies here too.** `HDP_EMBEDDING_MODEL`/
  `HDP_EMBEDDING_PROVIDER` are non-secret config, but if they also exist as
  Infisical secrets, `infisical-loader.sh` overwrites `.env` with the
  Infisical values on every container start — see
  [docker-services](docker-services.md#notable-patterns--gotchas).
- **Ranker model is always local**, regardless of embedding provider —
  `PM-AI/bi-encoder_msmarco_bert-base_german` is hardcoded in
  `hdp_pipeline.yaml` and pre-downloaded by `entrypoint.sh` even when
  `HDP_EMBEDDING_PROVIDER=remote`.
- **Dimension mismatch is a silent-until-query-time failure mode.**
  Nothing in `ingest_hdp_wiki.py` or `render_pipeline.py` validates that
  `HDP_EMBEDDING_DIM` actually matches the chosen model's real output
  dimensionality — a mismatch surfaces as OpenSearch mapping errors or
  garbage similarity scores, not a clear startup error.
