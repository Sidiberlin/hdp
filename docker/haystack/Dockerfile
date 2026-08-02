# Haystack RAG Pipeline — hayhooks server
# Runs the BlueSpice HDP AI pipeline for retrieval-augmented generation
#
# Build args:
#   HAYSTACK_DEVICE=cpu   (default) — CPU-only PyTorch, ~2GB image
#   HAYSTACK_DEVICE=gpu             — CUDA PyTorch, ~8GB image (needs NVIDIA runtime)
#
# In docker-compose.yml:
#   CPU:  just `docker compose build haystack`
#   GPU:  set HAYSTACK_DEVICE=gpu in .env, add deploy.resources.reservations.devices

ARG HAYSTACK_DEVICE=cpu

FROM python:3.11-slim-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        build-essential \
        curl \
        gettext-base \
        git \
        jq \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ---- CPU variant: install CPU-only PyTorch (~200MB vs ~2GB) ----
FROM base AS deps-cpu
RUN pip install --no-cache-dir \
    torch \
    --index-url https://download.pytorch.org/whl/cpu

# ---- GPU variant: install CUDA PyTorch ----
FROM base AS deps-gpu
RUN apt-get update && apt-get install -y --no-install-recommends \
        libgl1 \
        libglib2.0-0 \
    && pip install --no-cache-dir \
        torch \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ---- Final stage: common deps + pipeline on top of selected torch ----
FROM deps-${HAYSTACK_DEVICE} AS final

WORKDIR /opt/pipeline

RUN pip install --no-cache-dir \
    "haystack-ai==2.15.0" \
    "haystack-experimental==0.19.0.post1" \
    "hayhooks==1.10.0" \
    "opensearch-haystack==5.1.0" \
    "sentence-transformers==5.6.1" \
    "accelerate==1.14.0" \
    "transformers==5.14.1" \
    "jsonref==1.1.0" \
    "pymysql==1.2.0" \
    "requests==2.34.2" \
    "gradio_client>=2.5.0" \
    "pytest==9.0.2"
# pytest is here on purpose. It costs about 2MB on a ~2GB image and it is what
# makes the container self-sufficient for the tests/haystack tier: this image
# already ships gettext-base (for envsubst) and haystack-ai, so with pytest
# added, `docker compose exec haystack python -m pytest /tests` needs no network
# and no install step. The tests themselves are NOT copied in — they are
# bind-mounted read-only at run time, so no test code ships in the image.
# Keep the version in step with tests/requirements.txt.

COPY docker/haystack/hdp_pipeline.yaml /opt/pipeline/hdp_pipeline.yaml
COPY docker/haystack/render_pipeline.py /opt/pipeline/render_pipeline.py
COPY docker/haystack/entrypoint.sh /opt/pipeline/entrypoint.sh
COPY docker/haystack/ingest_hdp_wiki.py /opt/pipeline/ingest_hdp_wiki.py
COPY docker/haystack/wikitext.py /opt/pipeline/wikitext.py
COPY docker/haystack/serialization.py /opt/pipeline/serialization.py
COPY docker/haystack/hdp_api_server.py /opt/pipeline/hdp_api_server.py
COPY docker/infisical-loader.sh /infisical-loader.sh
RUN chmod +x /opt/pipeline/entrypoint.sh /infisical-loader.sh

# Label for documentation
LABEL org.opencontainers.image.title="BlueSpice HDP Haystack Pipeline"
LABEL org.opencontainers.image.description="RAG pipeline (hayhooks) for BlueSpice HDP — ${HAYSTACK_DEVICE} build"
LABEL hdp.device="${HAYSTACK_DEVICE}"

EXPOSE 1416 1417

ENTRYPOINT ["/opt/pipeline/entrypoint.sh"]
