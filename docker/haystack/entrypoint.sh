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
if [ "$HDP_EMBEDDING_PROVIDER" = "local" ]; then
    echo "Pre-downloading embedding models..."
    python3 -c "
from sentence_transformers import SentenceTransformer
models = [
    '${HDP_EMBEDDING_MODEL:-mixedbread-ai/deepset-mxbai-embed-de-large-v1}',
    'PM-AI/bi-encoder_msmarco_bert-base_german',
]
for m in models:
    print(f'Downloading {m}...')
    SentenceTransformer(m)
    print(f'  OK')
" || echo "WARNING: model pre-download failed. Models will be downloaded on first query."
else
    echo "Skipping local embedding model pre-download (HDP_EMBEDDING_PROVIDER=${HDP_EMBEDDING_PROVIDER})"
    echo "Still pre-downloading the cross-encoder ranker (always local)..."
    python3 -c "
from sentence_transformers import SentenceTransformer
SentenceTransformer('PM-AI/bi-encoder_msmarco_bert-base_german')
print('  OK')
" || echo "WARNING: ranker model pre-download failed. Will be downloaded on first query."
fi

# Render the query-time embedder component (local vs remote) into the
# pipeline YAML before hayhooks loads it. See render_pipeline.py for why
# this can't be done with plain ${VAR} substitution alone.
echo "Rendering pipeline embedder block for HDP_EMBEDDING_PROVIDER=${HDP_EMBEDDING_PROVIDER}..."
python3 "$PIPELINE_DIR/render_pipeline.py" "$PIPELINE_FILE"

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
