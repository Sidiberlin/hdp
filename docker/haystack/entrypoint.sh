#!/bin/bash
set -e

# Haystack/hayhooks entrypoint
# 1. Loads secrets from Infisical
# 2. Waits for OpenSearch
# 3. Pre-downloads embedding models
# 4. Starts hayhooks and deploys the pipeline

PIPELINE_DIR="/opt/pipeline"
PIPELINE_FILE="$PIPELINE_DIR/hdp_pipeline.yaml"
HAYHOOKS_PORT="${HAYHOOKS_PORT:-1416}"
# The RAG API port. Was hardcoded to 1417 further down, so unlike
# HAYHOOKS_PORT it could not be moved — docker-compose.yml now passes both.
HDP_PDF_PORT="${HDP_PDF_PORT:-1417}"

# ─── Load secrets from Infisical ────────────────────────────────────
echo "=== Loading secrets from Infisical ==="
if [ -f /infisical-loader.sh ]; then
    source /infisical-loader.sh
else
    echo "WARNING: /infisical-loader.sh not found. Using .env values."
fi

# Validate LLM credentials
if [ -z "$HDP_LLM_API_KEY" ]; then
    echo "WARNING: HDP_LLM_API_KEY not set. Pipeline will fail on LLM calls."
else
    echo "  LLM: ${HDP_LLM_MODEL:-glm-4.7} @ ${HDP_LLM_BASE_URL:-https://open.bigmodel.cn/api/paas/v4}"
fi

# Validate embedding provider config
HDP_EMBEDDING_PROVIDER="${HDP_EMBEDDING_PROVIDER:-local}"
case "$HDP_EMBEDDING_PROVIDER" in
    local)
        echo "  Embeddings: local (${HDP_EMBEDDING_MODEL:-mixedbread-ai/deepset-mxbai-embed-de-large-v1}, in-container CPU/GPU)"
        ;;
    remote)
        if [ -z "$HDP_EMBEDDING_BASE_URL" ]; then
            echo "ERROR: HDP_EMBEDDING_PROVIDER=remote requires HDP_EMBEDDING_BASE_URL. See .env.example."
            exit 1
        fi
        if [ -z "$HDP_EMBEDDING_API_KEY" ]; then
            echo "WARNING: HDP_EMBEDDING_API_KEY not set for HDP_EMBEDDING_PROVIDER=remote. This is fine for endpoints that don't require auth."
        fi
        echo "  Embeddings: remote (${HDP_EMBEDDING_MODEL} @ ${HDP_EMBEDDING_BASE_URL})"
        ;;
    *)
        echo "ERROR: HDP_EMBEDDING_PROVIDER='$HDP_EMBEDDING_PROVIDER' is invalid for the live pipeline."
        echo "       Valid values: local, remote. ('hf_space' is ingestion-only, see ingest_hdp_wiki.py --provider hf_space)"
        exit 1
        ;;
esac

# ─── GPU sanity check ────────────────────────────────────────────────
# HAYSTACK_DEVICE=gpu chose the CUDA PyTorch wheels at *build* time
# (docker/haystack/Dockerfile). This is the run-time half: confirm the wheel
# that got baked in can actually see a GPU before serving a single query.
#
# This is the fix for the actual production incident, not a wheel-selection
# bug as such — a GPU install that silently lands on CPU torch previously
# showed up nowhere. The container built, started, passed its healthcheck,
# and served every embedding on CPU with GPU utilization sitting at 0%, and
# nothing said so. HAYSTACK_DEVICE=gpu is an explicit operator choice (set by
# install.sh or by hand), so failing loudly here — rather than degrading to
# CPU — is correct: a slow CPU container that LOOKS like the fast GPU one it
# was configured to be is worse than one that refuses to start, because the
# CPU one hides the misconfiguration behind a healthcheck that still passes.
if [ "${HAYSTACK_DEVICE:-cpu}" = "gpu" ]; then
    echo "=== GPU check (HAYSTACK_DEVICE=gpu) ==="
    GPU_CHECK="$(python3 -c '
import torch
print("cuda_available=%s torch=%s cuda_build=%s" % (
    torch.cuda.is_available(), torch.__version__, torch.version.cuda))
if torch.cuda.is_available():
    print("device=%s" % torch.cuda.get_device_name(0))
' 2>&1)"
    echo "$GPU_CHECK"
    if ! printf '%s' "$GPU_CHECK" | grep -q 'cuda_available=True'; then
        echo ""
        echo "FATAL: HAYSTACK_DEVICE=gpu but torch.cuda.is_available() is False."
        echo "       This container would silently serve every embedding on CPU."
        echo "       Common causes:"
        echo "         - NVIDIA Container Toolkit not installed/configured on the host"
        echo "           (sudo apt-get install -y nvidia-container-toolkit &&"
        echo "            sudo nvidia-ctk runtime configure --runtime=docker &&"
        echo "            sudo systemctl restart docker)"
        echo "         - the compose service is not reserving a GPU device"
        echo "           (docker-compose.gpu.yml / docker-compose.prod-gpu.yml missing)"
        echo "         - HAYSTACK_CUDA_VERSION does not match this driver's CUDA ceiling"
        echo "           ('nvidia-smi' on the host prints the ceiling as 'CUDA Version:')"
        echo "       See README-DOCKER.md#gpu-inference. Set HAYSTACK_DEVICE=cpu and"
        echo "       rebuild if you want to run on CPU instead."
        exit 1
    fi
    echo "GPU check passed."
fi

# Wait for OpenSearch
OPENSEARCH_HOST="${OPENSEARCH_HOST:-opensearch}"
OPENSEARCH_PORT="${OPENSEARCH_PORT:-9200}"
OPENSEARCH_URL="https://${OPENSEARCH_HOST}:${OPENSEARCH_PORT}"

echo "=== Haystack Pipeline Entrypoint ==="
echo "Waiting for OpenSearch at ${OPENSEARCH_URL}..."

max_tries=90
try=0
# Hoisted out of the curl call so the secret-scanner suppression can sit on its
# own line with a reason. This is a shell parameter expansion, not a committed
# credential: OPENSEARCH_PASSWORD comes from .env or Infisical, and "admin" is
# the documented local-dev fallback that any real deployment overrides.
OS_AUTH="admin:${OPENSEARCH_PASSWORD:-admin}"  # gitleaks:allow
while ! curl -sk -u "$OS_AUTH" "${OPENSEARCH_URL}/_cluster/health" >/dev/null 2>&1; do
    try=$((try + 1))
    if [ $try -ge $max_tries ]; then
        echo "ERROR: OpenSearch not reachable after ${max_tries} tries."
        exit 1
    fi
    echo "  OpenSearch not ready (try ${try}/${max_tries}), retrying in 3s..."
    sleep 3
done
echo "OpenSearch is ready."

# Pre-download embedding models (avoids timeout during first query)
# Only needed for HDP_EMBEDDING_PROVIDER=local — remote mode has no local model.
#
# The ranker is pre-downloaded via sentence_transformers.CrossEncoder, not
# SentenceTransformer — that's not cosmetic. The ranker component
# (SentenceTransformersSimilarityRanker) loads its model through CrossEncoder
# at query time, which requires a real sequence-classification head. A prior
# ranker model (PM-AI/bi-encoder_msmarco_bert-base_german) was a bi-encoder
# with no such head; pre-downloading it via SentenceTransformer(...) "worked"
# (that loader doesn't need a classifier head) and never surfaced that the
# actual ranker was silently scoring every document with an untrained,
# randomly-initialized head. Using the same loader class here as the ranker
# actually uses means a future model swap that repeats this mistake fails
# loudly in this pre-download step instead of silently degrading answers.
if [ "$HDP_EMBEDDING_PROVIDER" = "local" ]; then
    echo "Pre-downloading embedding model..."
    python3 -c "
from sentence_transformers import SentenceTransformer
m = '${HDP_EMBEDDING_MODEL:-mixedbread-ai/deepset-mxbai-embed-de-large-v1}'
print(f'Downloading {m}...')
SentenceTransformer(m)
print(f'  OK')
" || echo "WARNING: embedding model pre-download failed. Model will be downloaded on first query."
else
    echo "Skipping local embedding model pre-download (HDP_EMBEDDING_PROVIDER=${HDP_EMBEDDING_PROVIDER})"
fi

echo "Pre-downloading the cross-encoder ranker (always local)..."
python3 -c "
from sentence_transformers import CrossEncoder
CrossEncoder('cross-encoder/msmarco-MiniLM-L6-en-de-v1')
print('  OK')
" || echo "WARNING: ranker model pre-download failed. Will be downloaded on first query."

# Render the query-time embedder component (local vs remote) into the
# pipeline YAML before hayhooks loads it. See render_pipeline.py for why
# this can't be done with plain ${VAR} substitution alone.
echo "Rendering pipeline embedder block for HDP_EMBEDDING_PROVIDER=${HDP_EMBEDDING_PROVIDER}..."
python3 "$PIPELINE_DIR/render_pipeline.py" "$PIPELINE_FILE"

# Re-assert the hayhooks numpy serialization patch. The Dockerfile applies this
# at build time and fails the build if it cannot, so on a stock image this is a
# no-op that costs one interpreter start — it re-reads the file, finds the
# marker and returns. It is here for what it prints: one line in the container
# log saying whether hayhooks' :1416 endpoint can serialize its own results, so
# `docker compose logs haystack | grep "numpy patch"` answers that question
# without exec'ing into the container. It also re-applies the patch if hayhooks
# was reinstalled inside a running container, which is the one way the built-in
# copy can go missing.
#
# Deliberately not fatal: the build-time apply is the guarantee, and a container
# that starts and serves the :1417 API is more useful than one that refuses to
# boot because a defensive re-check failed.
python3 -m hayhooks_numpy_patch \
    || echo "WARNING: could not verify the hayhooks numpy patch. Queries to hayhooks on :${HAYHOOKS_PORT} may fail to serialize (see docker/haystack/hayhooks_numpy_patch.py). The API on :${HDP_PDF_PORT} is unaffected."

# Start hayhooks server in background
echo "Starting hayhooks on port ${HAYHOOKS_PORT}..."
hayhooks run --host 0.0.0.0 --port "$HAYHOOKS_PORT" &
HAYHOOKS_PID=$!

# Wait for hayhooks to be ready
echo "Waiting for hayhooks to start..."
max_tries=30
try=0
while ! curl -s "http://localhost:${HAYHOOKS_PORT}/docs" >/dev/null 2>&1; do
    try=$((try + 1))
    if [ $try -ge $max_tries ]; then
        echo "ERROR: hayhooks did not start within ${max_tries} tries."
        exit 1
    fi
    sleep 1
done
echo "hayhooks is ready."

# Deploy the pipeline (hayhooks 1.10.0 API: POST /deploy-yaml with JSON body)
echo "Deploying HDP pipeline..."
PIPELINE_YAML=$(envsubst < "$PIPELINE_FILE")
DEPLOY_RESULT=$(curl -s -X POST "http://localhost:${HAYHOOKS_PORT}/deploy-yaml" \
    -H "Content-Type: application/json" \
    -d "$(jq -n --arg yaml "$PIPELINE_YAML" '{name: "hdp_pipeline", source_code: $yaml, overwrite: true}')")
echo "Deploy result: ${DEPLOY_RESULT}"

echo ""
echo "=== Haystack Pipeline is ready ==="
echo "hayhooks API: http://localhost:${HAYHOOKS_PORT}"
echo "Pipeline deployed. Use POST /hdp_pipeline/run to query."

# Start the API server in the background
echo ""
echo "Starting API server on port ${HDP_PDF_PORT}..."
export HDP_API_PORT="${HDP_PDF_PORT}"
python3 /opt/pipeline/hdp_api_server.py &
API_PID=$!
echo "API server started (PID: $API_PID)"
echo ""
echo "=== System ready ==="
echo "Hayhooks: http://localhost:${HAYHOOKS_PORT}"
echo "API Server: http://localhost:${HDP_PDF_PORT}"

# Wait for the background process
wait $HAYHOOKS_PID
