#!/bin/bash
# ============================================================
# Infisical Secret Loader for HDP Docker Services
#
# Sources at container start to pull secrets from Infisical
# REST API into environment variables. No secrets written to
# disk, logged, or committed.
#
# Usage (in docker-compose entrypoint or command):
#   source /infisical-loader.sh
#   # Secrets now available as env vars:
#   echo $HDP_DB_PASSWORD  # (don't actually echo this)
#
# Requires these env vars (passed via docker-compose environment):
#   INFISICAL_URL           - e.g. https://infisical.example.com
#   INFISICAL_PROJECT_ID    - Project UUID
#   INFISICAL_CLIENT_ID     - Machine identity client ID
#   INFISICAL_CLIENT_SECRET - Machine identity client secret
#   INFISICAL_ENV           - Environment slug (default: prod)
# ============================================================
set +H
unset HISTFILE 2>/dev/null
export HISTFILE=""

_INF_LOG_PREFIX="[infisical-loader]"

# ─── Configuration ──────────────────────────────────────────────────
_INF_URL="${INFISICAL_URL}"
_INF_PID="${INFISICAL_PROJECT_ID}"
_INF_CID="${INFISICAL_CLIENT_ID}"
_INF_CSECRET="${INFISICAL_CLIENT_SECRET}"
_INF_ENV="${INFISICAL_ENV:-prod}"

if [[ -z "$_INF_URL" ]] || [[ -z "$_INF_PID" ]] || \
   [[ -z "$_INF_CID" ]] || [[ -z "$_INF_CSECRET" ]]; then
    echo "${_INF_LOG_PREFIX} WARNING: Infisical credentials not set."
    echo "${_INF_LOG_PREFIX} Falling back to .env plaintext values."
    return 0 2>/dev/null || exit 0
fi

# ─── Authenticate ───────────────────────────────────────────────────
_inf_token=""
_inf_response=""
# The login body must not be passed as a curl argument: anything in argv is
# world-readable via /proc/<pid>/cmdline for the lifetime of the request, which
# would contradict this file's own no-secrets-on-disk-or-in-logs contract.
# `printf` is a bash builtin, so the secret never reaches another process's
# argv either, and the pipe keeps it off the filesystem. `--data @-` reads the
# body from stdin (and strips the trailing newline, unlike --data-binary).
_inf_response=$(printf 'clientId=%s&clientSecret=%s' "$_INF_CID" "$_INF_CSECRET" \
    | curl -s -X POST "${_INF_URL}/api/v1/auth/universal-auth/login" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        --data @- \
        --connect-timeout 10 \
        --max-time 15 2>/dev/null)

_inf_token=$(echo "$_inf_response" | jq -r '.accessToken // empty' 2>/dev/null)

if [[ -z "$_inf_token" ]]; then
    echo "${_INF_LOG_PREFIX} WARNING: Authentication failed."
    echo "${_INF_LOG_PREFIX} Falling back to .env plaintext values."
    return 0 2>/dev/null || exit 0
fi

echo "${_INF_LOG_PREFIX} Authenticated successfully."

# ─── Fetch secrets ──────────────────────────────────────────────────
# List all secrets and fetch each one. We only set env vars for
# HDP_* prefixed secrets to avoid polluting the environment.
_inf_secret_response=""
_inf_secret_response=$(curl -s -X GET \
    "${_INF_URL}/api/v4/secrets?environment=${_INF_ENV}&projectId=${_INF_PID}&secretPath=/&type=shared" \
    -H "Authorization: Bearer ${_inf_token}" \
    -H "Content-Type: application/json" \
    --connect-timeout 10 \
    --max-time 30 2>/dev/null)

# Extract HDP_ secret names
_inf_secret_names=$(echo "$_inf_secret_response" | jq -r '.secrets[] | select(.secretKey | startswith("HDP_")) | .secretKey' 2>/dev/null)

if [[ -z "$_inf_secret_names" ]]; then
    echo "${_INF_LOG_PREFIX} WARNING: No HDP_ secrets found in Infisical."
    return 0 2>/dev/null || exit 0
fi

# Fetch each secret value individually (values not included in list API)
# NOTE: All HDP secrets must be stored in Infisical with the HDP_ prefix.
# The LLM API key should be named HDP_LLM_API_KEY in Infisical.
_inf_count=0
while IFS= read -r _inf_name; do
    _inf_val=""
    _inf_val=$(curl -s -X GET \
        "${_INF_URL}/api/v4/secrets/$(printf '%s' "$_inf_name" | jq -sRr @uri)?environment=${_INF_ENV}&projectId=${_INF_PID}&secretPath=/&type=shared" \
        -H "Authorization: Bearer ${_inf_token}" \
        -H "Content-Type: application/json" \
        --connect-timeout 10 \
        --max-time 10 2>/dev/null | jq -r '.secret.secretValue // empty' 2>/dev/null)

    if [[ -n "$_inf_val" ]]; then
        export "${_inf_name}=${_inf_val}"
        _inf_count=$((_inf_count + 1))
    fi
done <<< "$_inf_secret_names"

echo "${_INF_LOG_PREFIX} Loaded ${_inf_count} secrets from Infisical."

# Clear sensitive intermediates
_inf_token=""
_inf_response=""
_inf_secret_response=""
