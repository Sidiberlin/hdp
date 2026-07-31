#!/bin/bash
# ============================================================
# HDP Docker Compose Wrapper with Infisical Secret Resolution
#
# Fetches all HDP_ secrets from Infisical and exports them as
# environment variables BEFORE docker-compose parses the YAML.
# This way no plaintext secrets ever appear in .env.
#
# Usage:
#   ./hdp.sh up -d        # start the stack
#   ./hdp.sh down         # stop the stack
#   ./hdp.sh exec mediawiki bash /setup.sh
#   ./hdp.sh logs -f haystack
#
# The .env file should contain:
#   INFISICAL_URL=https://...
#   INFISICAL_PROJECT_ID=...
#   INFISICAL_CLIENT_ID=...
#   INFISICAL_CLIENT_SECRET=...
#   # Plus non-secret config (ports, language, etc.)
# ============================================================
set -euo pipefail

# ─── Resolve script location ────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERROR: .env not found. Copy .env.example to .env first."
    exit 1
fi

# ─── Load Infisical config from .env ────────────────────────────────
INF_URL=$(grep '^INFISICAL_URL=' "$ENV_FILE" | head -1 | cut -d'=' -f2-)
INF_PID=$(grep '^INFISICAL_PROJECT_ID=' "$ENV_FILE" | head -1 | cut -d'=' -f2-)
INF_CID=$(grep '^INFISICAL_CLIENT_ID=' "$ENV_FILE" | head -1 | cut -d'=' -f2-)
INF_CSECRET=$(grep '^INFISICAL_CLIENT_SECRET=' "$ENV_FILE" | head -1 | cut -d'=' -f2-)
INF_ENV=$(grep '^INFISICAL_ENV=' "$ENV_FILE" | head -1 | cut -d'=' -f2-)
INF_ENV="${INF_ENV:-prod}"

# Strip quotes
for var in INF_URL INF_PID INF_CID INF_CSECRET INF_ENV; do
    declare "$var=${!var#\"}"
    declare "$var=${!var%\"}"
done

if [[ -z "$INF_URL" ]] || [[ -z "$INF_PID" ]] || \
   [[ -z "$INF_CID" ]] || [[ -z "$INF_CSECRET" ]]; then
    echo "ERROR: Infisical credentials missing in .env"
    echo "Required: INFISICAL_URL, INFISICAL_PROJECT_ID, INFISICAL_CLIENT_ID, INFISICAL_CLIENT_SECRET"
    exit 1
fi

# ─── Authenticate to Infisical ──────────────────────────────────────
echo "[hdp] Authenticating to Infisical..."

set +H
unset HISTFILE 2>/dev/null
export HISTFILE=""

AUTH_RESPONSE=$(curl -s -X POST "${INF_URL}/api/v1/auth/universal-auth/login" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "clientId=${INF_CID}&clientSecret=${INF_CSECRET}" \
    --connect-timeout 10 \
    --max-time 15 2>/dev/null)

TOKEN=$(echo "$AUTH_RESPONSE" | jq -r '.accessToken // empty' 2>/dev/null)

if [[ -z "$TOKEN" ]]; then
    echo "[hdp] ERROR: Infisical authentication failed"
    echo "$AUTH_RESPONSE" | jq -r '.message // "Unknown error"' 2>/dev/null
    exit 1
fi

echo "[hdp] Authenticated."

# ─── Fetch all HDP_ secrets ─────────────────────────────────────────
# NOTE: All HDP secrets must be stored in Infisical with the HDP_ prefix.
# The LLM API key should be named HDP_LLM_API_KEY in Infisical.
SECRET_RESPONSE=$(curl -s -X GET \
    "${INF_URL}/api/v4/secrets?environment=${INF_ENV}&projectId=${INF_PID}&secretPath=/&type=shared" \
    -H "Authorization: Bearer ${TOKEN}" \
    --connect-timeout 10 \
    --max-time 30 2>/dev/null)

SECRET_NAMES=$(echo "$SECRET_RESPONSE" | jq -r '.secrets[] | select(.secretKey | startswith("HDP_")) | .secretKey' 2>/dev/null)

LOADED=0
while IFS= read -r name; do
    [[ -z "$name" ]] && continue
    value=$(curl -s -X GET \
        "${INF_URL}/api/v4/secrets/$(printf '%s' "$name" | jq -sRr @uri)?environment=${INF_ENV}&projectId=${INF_PID}&secretPath=/&type=shared" \
        -H "Authorization: Bearer ${TOKEN}" \
        --connect-timeout 10 \
        --max-time 10 2>/dev/null | jq -r '.secret.secretValue // empty' 2>/dev/null)
    if [[ -n "$value" ]]; then
        export "$name=$value"
        LOADED=$((LOADED + 1))
    fi
done <<< "$SECRET_NAMES"

echo "[hdp] Loaded ${LOADED} secrets from Infisical."

# Clear token
TOKEN=""
AUTH_RESPONSE=""
SECRET_RESPONSE=""

# ─── Pass through to docker compose ─────────────────────────────────
cd "$SCRIPT_DIR"
exec docker compose "$@"
