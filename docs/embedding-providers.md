# Embedding Providers

The wiki-content embedder (used both by the live RAG query pipeline and by
the batch ingestion script) is configurable across three modes via
`HDP_EMBEDDING_PROVIDER` in `.env`. This doc explains each mode, when to use
it, and how the swap works under the hood.

## TL;DR

| Mode | `HDP_EMBEDDING_PROVIDER` | Used by | Setup effort |
|---|---|---|---|
| **Local** (default) | `local` | Live pipeline + ingestion | None — works out of the box |
| **Remote** | `remote` | Live pipeline + ingestion | Point at an OpenAI-compatible embeddings endpoint |
| **HF Space** | `hf_space` | Ingestion **only** | One-off fast bulk re-index via a HuggingFace ZeroGPU Space |

Only `local` and `remote` are valid for the live pipeline
(`docker-compose up`). `hf_space` is deliberately rejected by
`render_pipeline.py` if set as the deploy-time provider — see
[Why hf_space is ingestion-only](#why-hf_space-is-ingestion-only) below.

## Mode: local (default)

Runs [sentence-transformers](https://www.sbert.net/) directly inside the
`haystack` container, on CPU (or GPU if you build with
`HAYSTACK_DEVICE=gpu`). This is what upstream BlueSpice HDP Edition ships
with, and is the zero-config default — leave `HDP_EMBEDDING_PROVIDER` unset
or `local` and nothing else is needed.

```bash
HDP_EMBEDDING_PROVIDER=local
HDP_EMBEDDING_MODEL=mixedbread-ai/deepset-mxbai-embed-de-large-v1   # default
HDP_EMBEDDING_DIM=1024                                              # default
```

**Tradeoff**: on a small/shared host, embedding a full 224-page wiki this
way can take hours (~2-2.5 min/page observed on a 4-vCPU host with no GPU) —
each page's chunks are embedded synchronously, one page at a time. Query-time
embedding (a single short question) is fast regardless of hardware, so this
only matters for the initial bulk ingestion run, not for live chatbot
responsiveness.

## Mode: remote

Points the embedder at any **OpenAI-compatible embeddings API** — e.g. a
self-hosted [Text-Embeddings-Inference](https://github.com/huggingface/text-embeddings-inference)
(TEI) server, or a commercial embeddings API such as OpenAI's own.

```bash
HDP_EMBEDDING_PROVIDER=remote
HDP_EMBEDDING_BASE_URL=https://api.openai.com/v1     # or your TEI server URL
HDP_EMBEDDING_MODEL=text-embedding-3-small
HDP_EMBEDDING_DIM=1536                               # must match the model's actual output dim!
HDP_EMBEDDING_API_KEY=                                # blank if your endpoint needs no auth
```

**⚠️ `HDP_EMBEDDING_DIM` must match your chosen model's real output
dimension.** This value is baked into the OpenSearch index mapping
(`embedding_dim` on the `hdp_wiki` index). Changing embedding model/provider
after the index already has documents from a different-dimension model
means either: (a) you're switching before first ingestion (fine, index is
empty), or (b) you must fully re-ingest (`docker compose exec haystack
python3 ingest_hdp_wiki.py`) after wiping the index, since old and new
vectors aren't comparable.

This is the recommended path for production once you have a dedicated GPU
embedding server — much faster ingestion than `local` on a constrained host,
with no cold-start/rate-limit risk (unlike `hf_space`).

## Mode: hf_space (ingestion / testing only)

Embeds via a HuggingFace [ZeroGPU](https://huggingface.co/docs/hub/spaces-zerogpu)
Gradio Space, called through
[`gradio_client`](https://www.gradio.app/guides/getting-started-with-the-python-client).
Useful for a one-off fast bulk re-index without provisioning your own GPU —
observed **~75x faster** than `local` CPU embedding (~2s/page vs
~2.5min/page) for a 487M-parameter embedding model.

```bash
# NOT set as HDP_EMBEDDING_PROVIDER in .env — see below.
HDP_EMBEDDING_HF_SPACE_ID=your-username/your-embedder-space
HDP_EMBEDDING_HF_TOKEN=                               # your HF token (or use the shared HF_TOKEN var)
```

Run it as a one-off, not as the deployed pipeline config:

```bash
docker compose exec haystack python3 ingest_hdp_wiki.py --provider hf_space
docker compose exec haystack python3 ingest_hdp_wiki.py --provider hf_space --missing-only  # resume
```

### Deploying your own embedding Space

A minimal ZeroGPU Space that exposes a `sentence-transformers` model as a
gradio_client-compatible API:

```python
# app.py
import json
import gradio as gr
import spaces
from sentence_transformers import SentenceTransformer

model = None

def get_model():
    global model
    if model is None:
        model = SentenceTransformer("mixedbread-ai/deepset-mxbai-embed-de-large-v1")
    return model

@spaces.GPU
def embed_texts(texts_json: str) -> str:
    texts = json.loads(texts_json)
    m = get_model()
    embeddings = m.encode(texts, batch_size=64, convert_to_numpy=True)
    return json.dumps(embeddings.tolist())

demo = gr.Interface(fn=embed_texts, inputs=gr.Textbox(), outputs=gr.Textbox())
demo.launch()
```

```
# requirements.txt — do NOT pin gradio here, it's SDK-managed via README.md
sentence-transformers
```

```yaml
# README.md frontmatter — sdk_version pin matters, 4.44.0 has a gradio_client bug
---
title: My Embedder
sdk: gradio
sdk_version: "5.20.0"
app_file: app.py
---
```

Set ZeroGPU hardware via the Hub API after creating the Space (the README
`hardware:` key alone does not do this):

```python
from huggingface_hub import request_space_hardware
request_space_hardware("your-username/your-embedder-space", "zero-a10g", token=YOUR_WRITE_TOKEN)
```

### Why hf_space is ingestion-only

`render_pipeline.py` (which renders the live pipeline's `query_embedder`
component at container start) only implements `local` and `remote` — if
`HDP_EMBEDDING_PROVIDER=hf_space` is set, the `haystack` container refuses
to start with a clear error. This is deliberate:

- **Cold starts**: an idle ZeroGPU Space can take 10-60s to wake up on the
  first request after inactivity — unacceptable latency for a live chat
  query.
- **Per-session quotas**: ZeroGPU enforces rolling usage limits per
  HF account/session. A single busy chatbot could exhaust the quota and
  start failing user queries with no local fallback.
- **Network dependency**: the live pipeline would gain a hard runtime
  dependency on HuggingFace's infrastructure being up, for every single
  query — bulk ingestion tolerates this far better (it's a one-off batch
  job you're watching, not a 24/7 service).

Bulk ingestion doesn't have these problems: it's a bounded, supervised,
one-time (or occasional) job, and the built-in retry/backoff in
`HFSpaceEmbedder._call()` handles transient quota errors gracefully.

## How the swap works (implementation notes)

Haystack pipeline YAML declares component **types** statically — you can't
branch which Python class gets instantiated using plain `${VAR}`
substitution, since `SentenceTransformersTextEmbedder` (local) and
`OpenAITextEmbedder` (remote) are different classes with different required
`init_parameters`.

`docker/haystack/render_pipeline.py` solves this by text-replacing the
`query_embedder` component block (delimited by `HDP_EMBEDDER_BLOCK_START`/
`_END` sentinel comments in `hdp_pipeline.yaml`) with the correct block for
the configured provider, **before** `envsubst` and hayhooks ever load the
file. This runs once at container start (`entrypoint.sh`), so switching
`HDP_EMBEDDING_PROVIDER` just requires `docker compose up -d --force-recreate haystack`
— no manual YAML editing.

The ingestion script (`ingest_hdp_wiki.py`) uses a small provider
abstraction instead (`LocalEmbedder` / `RemoteEmbedder` / `HFSpaceEmbedder`,
selected by `make_embedder()`), since it's plain Python and doesn't have
the YAML-vs-Python-class constraint.
