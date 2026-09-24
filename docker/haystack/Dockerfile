# Haystack RAG Pipeline — hayhooks server
# Runs the BlueSpice HDP AI pipeline for retrieval-augmented generation
#
# Build args:
#   HAYSTACK_DEVICE=cpu   (default) — CPU-only PyTorch, ~2GB image
#   HAYSTACK_DEVICE=gpu             — CUDA PyTorch, ~8GB image (needs NVIDIA runtime)
#   HAYSTACK_CUDA_VERSION=cu124 (default) — which CUDA PyTorch build, gpu only
#
# In docker-compose.yml:
#   CPU:  just `docker compose build haystack`
#   GPU:  set HAYSTACK_DEVICE=gpu in .env, add deploy.resources.reservations.devices

ARG HAYSTACK_DEVICE=cpu

# Declared here as well as inside deps-gpu, for two reasons that are both about
# builds that do not use it: a global ARG is what makes BuildKit accept the
# value on a CPU build instead of warning that the build-arg was not consumed
# (docker-compose.yml passes both args for either device), and it is where you
# look for the default. The stage below re-declares it because a global ARG is
# out of scope inside a build stage — only the FROM lines can read it.
ARG HAYSTACK_CUDA_VERSION=cu124

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
# The index URL is not optional decoration. Without it pip takes whatever CUDA
# build PyPI currently defaults to — cu124 today — and NVIDIA drivers are
# backward compatible but not forward compatible: a driver that tops out at
# CUDA 12.0 loads a cu118 torch fine and dies on a cu124 one with "CUDA driver
# version is insufficient for CUDA runtime version", at model load, inside the
# container, long after the build looked like it worked. install.sh reads the
# host driver's ceiling out of nvidia-smi and pins this arg accordingly.
#
# cu124 stays the default so the published -gpu image and every existing build
# are unchanged. Only deps-gpu carries the index URL, so the CPU stage above
# still gets its CPU-only wheels.
FROM base AS deps-gpu
ARG HAYSTACK_CUDA_VERSION=cu124
RUN apt-get update && apt-get install -y --no-install-recommends \
        libgl1 \
        libglib2.0-0 \
    && pip install --no-cache-dir \
        torch \
        --index-url "https://download.pytorch.org/whl/${HAYSTACK_CUDA_VERSION}" \
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

# ---- Patch hayhooks for numpy serialization ----
# hayhooks' own /{pipeline}/run endpoint (:1416) hands the pipeline result to a
# Pydantic response model, and Haystack puts numpy.float32 in Document.score —
# which Pydantic cannot serialize. A successful query returns 500. Upstream has
# not fixed this as of 1.23.0; the module's docstring has the detail.
#
# This RUN must stay *after* the pip install above: it edits an installed file,
# so a reordering would have pip overwrite the patch. It fails the build if the
# anchor is not found, which is what a hayhooks version bump will do — that is
# the intent, not a bug. The module is installed into site-packages (rather
# than /opt/pipeline) because the patched deploy_utils.py imports it from
# inside site-packages, where /opt/pipeline is not on sys.path.
COPY docker/haystack/hayhooks_numpy_patch.py /tmp/hayhooks_numpy_patch.py
RUN SITE_PACKAGES="$(python3 -c 'import sysconfig; print(sysconfig.get_paths()["purelib"])')" \
    && cp /tmp/hayhooks_numpy_patch.py "$SITE_PACKAGES/hayhooks_numpy_patch.py" \
    && rm /tmp/hayhooks_numpy_patch.py \
    && python3 -m hayhooks_numpy_patch

COPY docker/haystack/hdp_pipeline.yaml /opt/pipeline/hdp_pipeline.yaml
COPY docker/haystack/render_pipeline.py /opt/pipeline/render_pipeline.py
COPY docker/haystack/entrypoint.sh /opt/pipeline/entrypoint.sh
COPY docker/haystack/ingest_hdp_wiki.py /opt/pipeline/ingest_hdp_wiki.py
COPY docker/haystack/wikitext.py /opt/pipeline/wikitext.py
COPY docker/haystack/ingest_select.py /opt/pipeline/ingest_select.py
COPY docker/haystack/serialization.py /opt/pipeline/serialization.py
COPY docker/haystack/hdp_api_server.py /opt/pipeline/hdp_api_server.py
COPY docker/haystack/ingest_api.py /opt/pipeline/ingest_api.py
COPY docker/haystack/ingest_validate.py /opt/pipeline/ingest_validate.py
COPY docker/haystack/ingest-scheduler.sh /opt/pipeline/ingest-scheduler.sh
COPY docker/infisical-loader.sh /infisical-loader.sh
RUN chmod +x /opt/pipeline/entrypoint.sh /opt/pipeline/ingest-scheduler.sh /infisical-loader.sh

# Re-declared: the ARG above the first FROM is only in scope for the FROM lines
# themselves, so without this the two labels below interpolated to the empty
# string and every image claimed hdp.device="". That was cosmetic while only one
# variant was ever published; now that release.yml pushes both a CPU and a GPU
# image, this label is how you tell a pulled image apart from its twin.
ARG HAYSTACK_DEVICE

# Label for documentation
LABEL org.opencontainers.image.title="BlueSpice HDP Haystack Pipeline"
LABEL org.opencontainers.image.description="RAG pipeline (hayhooks) for BlueSpice HDP — ${HAYSTACK_DEVICE} build"
LABEL hdp.device="${HAYSTACK_DEVICE}"

EXPOSE 1416 1417

ENTRYPOINT ["/opt/pipeline/entrypoint.sh"]
