#!/usr/bin/env bash
# ============================================================
# install.sh — the one command that stands this stack up.
#
#   curl -fsSL https://raw.githubusercontent.com/Sidiberlin/hdp/main/install.sh | bash
#
# or, from a checkout:
#
#   ./install.sh
#
# It clones the repository if it is not already in one, checks the three
# prerequisites (docker, compose v2, openssl), walks every configuration choice
# .env.example documents, writes the answers to .env, and offers to start the
# stack. Nothing here is irreversible: an existing .env is copied to .env.bak
# before a single line is changed, and every question is pre-filled with the
# value that .env already holds — so re-running this on a configured install is
# a way to change one setting, not a way to lose the rest.
#
# ─── Why every read is from fd 3 ────────────────────────────────────
#
# Under `curl … | bash` the script *is* stdin. A bare `read` would consume the
# not-yet-executed remainder of this file and the installer would silently stop
# partway through. fd 3 is opened on /dev/tty once, at the top, and every
# prompt reads from it; with no controlling terminal the script refuses to run
# rather than guessing answers.
#
# Exit: 0 configured (and started, if asked) · 1 prerequisite or user abort
# ============================================================
set -uo pipefail

REPO_URL="${HDP_REPO_URL:-https://github.com/Sidiberlin/hdp.git}"
CLONE_DIR="${HDP_CLONE_DIR:-hdp}"

# ─── Output ─────────────────────────────────────────────────────────
if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
    C_RED=$'\033[0;31m'; C_GRN=$'\033[0;32m'; C_YEL=$'\033[0;33m'
    C_BLU=$'\033[0;36m'; C_DIM=$'\033[0;90m'; C_BLD=$'\033[1m'; C_OFF=$'\033[0m'
else
    C_RED=''; C_GRN=''; C_YEL=''; C_BLU=''; C_DIM=''; C_BLD=''; C_OFF=''
fi

RULE='──────────────────────────────────────────────────────────────────'

step()  { printf '\n%s%s%s\n %s%s%s\n%s%s%s\n\n' \
              "$C_DIM" "$RULE" "$C_OFF" "$C_BLD$C_BLU" "$1" "$C_OFF" \
              "$C_DIM" "$RULE" "$C_OFF"; }
info()  { printf '  %s\n' "$1"; }
note()  { printf '  %s%s%s\n' "$C_DIM" "$1" "$C_OFF"; }
ok()    { printf '  %s✓%s %s\n' "$C_GRN" "$C_OFF" "$1"; }
warn()  { printf '  %s!%s %s\n' "$C_YEL" "$C_OFF" "$1"; }
die()   { printf '\n  %sERROR%s %s\n\n' "$C_RED" "$C_OFF" "$1" >&2; exit 1; }

usage() {
    cat <<EOF
${C_BLD}install.sh${C_OFF} — interactive setup for BlueSpice HDP + RAG chatbot.

  curl -fsSL https://raw.githubusercontent.com/Sidiberlin/hdp/main/install.sh | bash
  ./install.sh                 from a checkout

Environment overrides:
  HDP_REPO_URL     clone source          (default: $REPO_URL)
  HDP_CLONE_DIR    destination directory (default: ./$CLONE_DIR)
  NO_COLOR         disable colour output

The wizard writes .env only. It never commits, never pushes, and backs an
existing .env up to .env.bak before changing anything.
EOF
}

case "${1:-}" in
    --help|-h) usage; exit 0 ;;
    '') ;;
    *) printf 'install.sh: unknown option %s (try --help)\n' "$1" >&2; exit 2 ;;
esac

# ─── A terminal to ask questions on ─────────────────────────────────
# See the header: stdin belongs to curl, so answers come from /dev/tty or the
# script does not run at all. Refusing beats defaulting — the passwords and the
# LLM key have no safe default.
# The brace group is load-bearing. `exec 3</dev/tty 2>/dev/null` applies BOTH
# redirections to the shell itself, so on a host where /dev/tty opens — every
# normal host — stderr is silently sent to /dev/null for the rest of the run,
# and the git clone failure, the compose failure and every die() message go
# with it. Verified: the script exited 1 with no output at all. Scoping the
# 2>/dev/null to the group suppresses only bash's own "No such device" line,
# which is the single thing it was meant to suppress.
if ! { exec 3</dev/tty; } 2>/dev/null; then
    printf 'install.sh needs an interactive terminal and has none.\n' >&2
    printf 'Clone the repository and run it directly instead:\n\n' >&2
    printf '  git clone %s && cd %s && ./install.sh\n\n' "$REPO_URL" "$CLONE_DIR" >&2
    exit 1
fi

# ─── Prompts ────────────────────────────────────────────────────────
# All four set REPLY_VALUE / REPLY_CHOICE rather than echoing, so a caller can
# use them without a subshell — command substitution would reopen fd 3's
# buffering questions and lose the tty semantics `read -s` needs.
REPLY_VALUE=''
REPLY_CHOICE=0

ask() {  # ask <question> [default]
    local question="$1" default="${2:-}" answer=''
    if [ -n "$default" ]; then
        printf '  %s %s[%s]%s: ' "$question" "$C_DIM" "$default" "$C_OFF"
    else
        printf '  %s: ' "$question"
    fi
    IFS= read -r answer <&3 || answer=''
    REPLY_VALUE="${answer:-$default}"
}

ask_secret() {  # ask_secret <question> — input is not echoed
    local question="$1" answer=''
    printf '  %s: ' "$question"
    IFS= read -rs answer <&3 || answer=''
    printf '\n'
    REPLY_VALUE="$answer"
}

ask_choice() {  # ask_choice <question> <default-index> <label>...
    local question="$1" default="$2"
    shift 2
    local labels=("$@") i answer=''
    printf '  %s%s%s\n' "$C_BLD" "$question" "$C_OFF"
    for i in "${!labels[@]}"; do
        printf '    %s%d%s) %s\n' "$C_BLU" "$((i + 1))" "$C_OFF" "${labels[$i]}"
    done
    while :; do
        printf '  choice %s[%s]%s: ' "$C_DIM" "$default" "$C_OFF"
        IFS= read -r answer <&3 || answer=''
        answer="${answer:-$default}"
        if [ "$answer" -ge 1 ] 2>/dev/null && [ "$answer" -le "${#labels[@]}" ]; then
            REPLY_CHOICE="$answer"
            printf '\n'
            return 0
        fi
        warn "pick a number between 1 and ${#labels[@]}"
    done
}

confirm() {  # confirm <question> <y|n default> — returns 0 for yes
    local question="$1" default="${2:-y}" answer='' hint='[Y/n]'
    [ "$default" = "n" ] && hint='[y/N]'
    while :; do
        printf '  %s %s%s%s: ' "$question" "$C_DIM" "$hint" "$C_OFF"
        IFS= read -r answer <&3 || answer=''
        answer="${answer:-$default}"
        case "$answer" in
            y|Y|yes|YES) return 0 ;;
            n|N|no|NO)   return 1 ;;
            *) warn "answer y or n" ;;
        esac
    done
}

# ─── Locate the repository ──────────────────────────────────────────
step "BlueSpice HDP — installer"

info "Enterprise wiki (MediaWiki/BlueSpice) + self-hosted RAG chatbot."
note "This wizard writes .env and, if you want, starts the Docker stack."

# When piped from curl, BASH_SOURCE[0] is the string 'bash' rather than a path,
# so the -f test is what distinguishes "running from a checkout" from "running
# from a pipe" — not the presence of the variable.
SCRIPT_SRC="${BASH_SOURCE[0]:-}"
REPO_ROOT=''
if [ -n "$SCRIPT_SRC" ] && [ -f "$SCRIPT_SRC" ]; then
    SCRIPT_DIR="$(cd "$(dirname "$SCRIPT_SRC")" && pwd)"
    [ -f "$SCRIPT_DIR/docker-compose.yml" ] && REPO_ROOT="$SCRIPT_DIR"
fi
# Someone may also have cd'd into a checkout and then pasted the one-liner.
if [ -z "$REPO_ROOT" ] && [ -f "$PWD/docker-compose.yml" ] && [ -f "$PWD/.env.example" ]; then
    REPO_ROOT="$PWD"
fi

NEED_CLONE=0
[ -z "$REPO_ROOT" ] && NEED_CLONE=1

# ─── Prerequisites ──────────────────────────────────────────────────
step "1/8  Prerequisites"

have() { command -v "$1" >/dev/null 2>&1; }

MISSING=0
report_missing() {  # report_missing <tool> <how to install>
    printf '  %s✗%s %-16s %s%s%s\n' "$C_RED" "$C_OFF" "$1" "$C_DIM" "$2" "$C_OFF"
    MISSING=$((MISSING + 1))
}

if have docker; then
    ok "docker            $(docker --version 2>/dev/null | head -1)"
else
    report_missing docker "curl -fsSL https://get.docker.com | sh   (or docs.docker.com/engine/install)"
fi

COMPOSE_VERSION=''
if have docker && docker compose version >/dev/null 2>&1; then
    COMPOSE_VERSION="$(docker compose version --short 2>/dev/null | sed 's/^v//')"
    ok "docker compose    v${COMPOSE_VERSION:-2 (version unknown)}"
else
    report_missing "docker compose" "install the compose v2 plugin (apt: docker-compose-plugin). v1 'docker-compose' will not work."
fi

if have openssl; then
    ok "openssl           $(openssl version 2>/dev/null)"
else
    report_missing openssl "apt-get install -y openssl   |   dnf install -y openssl"
fi

if [ "$NEED_CLONE" -eq 1 ]; then
    if have git; then
        ok "git               $(git --version 2>/dev/null)"
    else
        report_missing git "apt-get install -y git   |   dnf install -y git"
    fi
fi

[ "$MISSING" -eq 0 ] || die "$MISSING prerequisite(s) missing — install them and re-run this script."

# The daemon not running is not fatal here: .env can still be written, only the
# final "start it now" step needs it. Say so once instead of failing late.
DOCKER_READY=1
if ! docker info >/dev/null 2>&1; then
    DOCKER_READY=0
    warn "the Docker daemon is not responding (try: sudo systemctl start docker)."
    note "Configuration will still be written; you can start the stack yourself afterwards."
fi

# compose >= 2.24 is what the published-image override needs — it uses the
# !reset tag to delete the inherited build: keys. Older compose fails to parse
# that file, so know now rather than at `up`.
COMPOSE_HAS_RESET=1
if [ -n "$COMPOSE_VERSION" ]; then
    if [ "$(printf '2.24.0\n%s\n' "$COMPOSE_VERSION" | sort -V | head -1)" != "2.24.0" ]; then
        COMPOSE_HAS_RESET=0
        warn "compose v$COMPOSE_VERSION is older than 2.24 — the pre-built-image path needs an explicit pull."
    fi
fi

# ─── Clone ──────────────────────────────────────────────────────────
if [ "$NEED_CLONE" -eq 1 ]; then
    step "2/8  Repository"

    if [ -d "$CLONE_DIR" ]; then
        if [ -f "$CLONE_DIR/docker-compose.yml" ]; then
            info "Found an existing checkout at ./$CLONE_DIR"
            confirm "Use it?" y || die "Nothing to do — move ./$CLONE_DIR aside or set HDP_CLONE_DIR."
            REPO_ROOT="$(cd "$CLONE_DIR" && pwd)"
        else
            die "./$CLONE_DIR exists and is not an HDP checkout. Move it aside or set HDP_CLONE_DIR=<dir>."
        fi
    else
        info "Cloning $REPO_URL into ./$CLONE_DIR"
        note "The wiki source (MediaWiki core + ~130 extensions) is vendored, so this is ~500 MB."
        note "A shallow clone is used; it still takes a few minutes on a slow link."
        printf '\n'
        git clone --depth 1 "$REPO_URL" "$CLONE_DIR" \
            || die "git clone failed — check the URL and your network."
        REPO_ROOT="$(cd "$CLONE_DIR" && pwd)"
        printf '\n'
        ok "Cloned into $REPO_ROOT"
    fi
else
    step "2/8  Repository"
    ok "Running inside an existing checkout: $REPO_ROOT"
fi

cd "$REPO_ROOT" || die "cannot enter $REPO_ROOT"
[ -f .env.example ] || die "$REPO_ROOT/.env.example is missing — this does not look like a complete checkout."

ENV_FILE="$REPO_ROOT/.env"

# ─── .env ───────────────────────────────────────────────────────────
step "3/8  Configuration file"

ENV_WAS_CREATED=0
if [ -f "$ENV_FILE" ]; then
    cp -p "$ENV_FILE" "$ENV_FILE.bak" || die "could not back up .env"
    ok "Existing .env backed up to .env.bak — its values become the defaults below."
else
    cp .env.example "$ENV_FILE" || die "could not create .env from .env.example"
    ENV_WAS_CREATED=1
    ok "Created .env from .env.example"
fi
chmod 600 "$ENV_FILE" 2>/dev/null || true

# ─── Interrupt handling ─────────────────────────────────────────────
# Installed here and not at the top of the script on purpose: before this point
# a Ctrl-C leaves nothing behind, and a trap that fires then would print a
# cleanup message about a file it never touched.
#
# From here on there IS partial state. The wizard writes .env key by key as the
# answers come in, so an interrupt halfway through leaves a file that is neither
# the old configuration nor a complete new one — some values set, the rest still
# the placeholders from .env.example. That file is worse than either end state:
# `docker compose up` accepts it and the stack fails later, on a password that
# is literally "changeme".
#
# So: restore the backup if we made one, delete the file if we created it, and
# say which happened. 130 is the conventional exit for SIGINT (128 + 2).
#
# ENV_WAS_CREATED is tested before the backup, not after: a .env.bak left by an
# earlier run survives a `rm .env`, so on a host in that state the two
# conditions are both true and restoring would resurrect a backup of an install
# this run knows nothing about. What this run made, this run removes.
on_interrupt() {
    trap - INT TERM
    printf '\n\n'
    if [ "$ENV_WAS_CREATED" -eq 1 ]; then
        rm -f "$ENV_FILE"
        warn "Setup interrupted. The partially written .env has been removed."
    elif [ -f "$ENV_FILE.bak" ]; then
        if cp -p "$ENV_FILE.bak" "$ENV_FILE" 2>/dev/null; then
            warn "Setup interrupted. .env has been restored from .env.bak."
        else
            warn "Setup interrupted, and .env could NOT be restored automatically."
            note "  Your previous configuration is still in $ENV_FILE.bak — copy it back by hand."
        fi
    else
        warn "Setup interrupted."
    fi
    info "Re-run ./install.sh to try again."
    printf '\n'
    exec 3<&- 2>/dev/null || true
    exit 130
}
trap on_interrupt INT TERM

# get_env <key> — first uncommented assignment, quotes stripped.
get_env() {
    local line
    line="$(grep -m1 "^$1=" "$ENV_FILE" 2>/dev/null || true)"
    line="${line#"$1"=}"
    line="${line%\"}"
    line="${line#\"}"
    printf '%s' "$line"
}

# set_env <key> <value> — replaces the first assignment in place, appends if the
# key is absent. The value travels through the environment rather than through
# an awk -v or a sed replacement, so API keys and generated passwords cannot be
# mangled by a backslash, an ampersand or a slash inside them.
set_env() {
    local key="$1" tmp
    tmp="$(mktemp)" || die "mktemp failed"
    HDP_SET_VALUE="$2" awk -v key="$key" '
        BEGIN { written = 0 }
        !written && index($0, key "=") == 1 {
            print key "=" ENVIRON["HDP_SET_VALUE"]; written = 1; next
        }
        { print }
        END { if (!written) print key "=" ENVIRON["HDP_SET_VALUE"] }
    ' "$ENV_FILE" >"$tmp" || { rm -f "$tmp"; die "could not write $ENV_FILE"; }
    cat "$tmp" >"$ENV_FILE" || { rm -f "$tmp"; die "could not write $ENV_FILE"; }
    rm -f "$tmp"
}

# ─── 4a. Secrets backend ────────────────────────────────────────────
step "4/8  Secrets backend"

info "Where should passwords and API keys live?"
printf '\n'
ask_choice "Secret storage" 1 \
    "Plain .env  ${C_DIM}(simplest — values are written to .env, which is gitignored)${C_OFF}" \
    "Infisical   ${C_DIM}(for teams/production — .env holds only a machine identity)${C_OFF}"

SECRETS_BACKEND='plain .env'
if [ "$REPLY_CHOICE" -eq 2 ]; then
    SECRETS_BACKEND='Infisical'
    info "Create a Machine Identity in Infisical → Settings → Machine Identities."
    printf '\n'
    ask "Infisical URL" "$(get_env HDP_INFISICAL_URL)"
    set_env HDP_INFISICAL_URL "$REPLY_VALUE"
    ask "Project ID" "$(get_env HDP_INFISICAL_PROJECT_ID)"
    set_env HDP_INFISICAL_PROJECT_ID "$REPLY_VALUE"
    ask "Client ID" "$(get_env HDP_INFISICAL_CLIENT_ID)"
    set_env HDP_INFISICAL_CLIENT_ID "$REPLY_VALUE"
    ask_secret "Client secret (hidden)"
    if [ -n "$REPLY_VALUE" ]; then
        set_env HDP_INFISICAL_CLIENT_SECRET "$REPLY_VALUE"
    else
        note "left unchanged"
    fi
    ask "Environment slug" "$(get_env HDP_INFISICAL_ENV)"
    set_env HDP_INFISICAL_ENV "${REPLY_VALUE:-prod}"
    printf '\n'
    ok "Infisical configured — secrets whose names start with HDP_ are fetched at container start."
    warn "HDP_OPENSEARCH_PASSWORD must ALSO be in .env: compose needs it before any"
    note "  Infisical-aware process runs. The password step below still applies."
else
    # Clearing rather than leaving the .env.example placeholder: infisical-loader.sh
    # skips itself only when the client ID/secret are empty, and a half-filled
    # block is the ambiguous state that makes "which value won?" hard to answer.
    set_env HDP_INFISICAL_URL ""
    set_env HDP_INFISICAL_PROJECT_ID ""
    set_env HDP_INFISICAL_CLIENT_ID ""
    set_env HDP_INFISICAL_CLIENT_SECRET ""
    ok "Using plain .env — the Infisical block is cleared, so the loader skips itself."
fi

# ─── 4b. Wiki settings ──────────────────────────────────────────────
step "5/8  Wiki"

ask "Site name" "$(get_env MW_SITENAME)"
MW_SITENAME_VAL="${REPLY_VALUE:-BlueSpice HDP}"
set_env MW_SITENAME "$MW_SITENAME_VAL"

ask "Content language (de, en, fr, …)" "$(get_env MW_LANG)"
MW_LANG_VAL="${REPLY_VALUE:-de}"
set_env MW_LANG "$MW_LANG_VAL"

while :; do
    ask "Host port for the wiki" "$(get_env MW_DOCKER_PORT)"
    MW_PORT_VAL="${REPLY_VALUE:-8080}"
    case "$MW_PORT_VAL" in
        ''|*[!0-9]*) warn "the port must be a number" ;;
        *) [ "$MW_PORT_VAL" -ge 1 ] && [ "$MW_PORT_VAL" -le 65535 ] && break
           warn "the port must be between 1 and 65535" ;;
    esac
done
set_env MW_DOCKER_PORT "$MW_PORT_VAL"

printf '\n'
note "MW_SERVER is the base URL MediaWiki puts in every link and redirect."
note "Keep localhost only for single-machine testing — from another device,"
note "logins would redirect the browser to its own localhost and fail."
ask "Server URL" "http://localhost:$MW_PORT_VAL"
MW_SERVER_VAL="$REPLY_VALUE"
set_env MW_SERVER "$MW_SERVER_VAL"

# ─── 4c. LLM provider ───────────────────────────────────────────────
step "6/8  LLM provider (the chatbot's answer generator)"

info "Any OpenAI-compatible chat-completions endpoint works."
printf '\n'
ask_choice "Provider" 1 \
    "OpenAI      ${C_DIM}api.openai.com/v1${C_OFF}" \
    "z.ai / GLM  ${C_DIM}api.z.ai/api/coding/paas/v4${C_OFF}" \
    "Nebius      ${C_DIM}api.studio.nebius.ai/v1${C_OFF}" \
    "Custom      ${C_DIM}self-hosted vLLM, Ollama, Azure, …${C_OFF}"

case "$REPLY_CHOICE" in
    1) LLM_PROVIDER='OpenAI';    LLM_BASE_URL='https://api.openai.com/v1';          LLM_MODEL_DEFAULT='gpt-4o' ;;
    2) LLM_PROVIDER='z.ai GLM';  LLM_BASE_URL='https://api.z.ai/api/coding/paas/v4'; LLM_MODEL_DEFAULT='glm-4.5-air' ;;
    3) LLM_PROVIDER='Nebius';    LLM_BASE_URL='https://api.studio.nebius.ai/v1';    LLM_MODEL_DEFAULT='qwen-235b' ;;
    *) LLM_PROVIDER='Custom'
       ask "Base URL (must end in the OpenAI-compatible path, e.g. /v1)" "$(get_env HDP_LLM_BASE_URL)"
       LLM_BASE_URL="$REPLY_VALUE"
       LLM_MODEL_DEFAULT="$(get_env HDP_LLM_MODEL)" ;;
esac
[ -n "$LLM_BASE_URL" ] || die "an LLM base URL is required"
set_env HDP_LLM_BASE_URL "$LLM_BASE_URL"

ask "Model name" "$LLM_MODEL_DEFAULT"
LLM_MODEL_VAL="$REPLY_VALUE"
[ -n "$LLM_MODEL_VAL" ] || die "an LLM model name is required"
set_env HDP_LLM_MODEL "$LLM_MODEL_VAL"

printf '\n'
LLM_KEY_EXISTING="$(get_env HDP_LLM_API_KEY)"
if [ -n "$LLM_KEY_EXISTING" ]; then
    note "An API key is already set; press Enter to keep it."
fi
ask_secret "API key for $LLM_PROVIDER (hidden)"
LLM_KEY_STATE='kept from existing .env'
if [ -n "$REPLY_VALUE" ]; then
    set_env HDP_LLM_API_KEY "$REPLY_VALUE"
    LLM_KEY_STATE='set'
elif [ -z "$LLM_KEY_EXISTING" ]; then
    LLM_KEY_STATE='NOT SET'
    if [ "$SECRETS_BACKEND" = "Infisical" ]; then
        note "Left blank — store it in Infisical as HDP_LLM_API_KEY."
        LLM_KEY_STATE='from Infisical'
    else
        warn "No key set. The chatbot cannot answer until HDP_LLM_API_KEY is filled in."
    fi
fi

# ─── 4d. Embedding provider ─────────────────────────────────────────
step "7/8  Embedding provider (how wiki pages are indexed)"

info "This embedder is used both at ingestion time and for every live query."
printf '\n'

# GPU detection happens up front so it can be part of the choice menu.
# nvidia-smi is absent inside containers that do have a GPU, and /proc/driver/nvidia
# exists whenever the kernel module is loaded, even with no CLI tools installed.
GPU_DETECTED=0
GPU_NAMES=''
if have nvidia-smi && nvidia-smi -L >/dev/null 2>&1; then
    GPU_DETECTED=1
    GPU_NAMES="$(nvidia-smi --query-gpu=name --format=csv,noheader 2>/dev/null | paste -sd', ' - || true)"
elif [ -d /proc/driver/nvidia ]; then
    GPU_DETECTED=1
fi
if [ "$GPU_DETECTED" -eq 1 ]; then
    ok "NVIDIA GPU detected${GPU_NAMES:+: $GPU_NAMES}"
fi

# The menu changes based on whether a GPU is visible. When detected, "Local GPU"
# is the default (it's why you'd run on a GPU box). When not detected, GPU is
# still listed — the installer may be running inside a container that can't see
# the host's GPU, and the user knows their hardware.
if [ "$GPU_DETECTED" -eq 1 ]; then
    DEFAULT_EMBED=3
else
    DEFAULT_EMBED=1
fi
ask_choice "Embeddings" "$DEFAULT_EMBED" \
    "Local CPU   ${C_DIM}(zero config — sentence-transformers in-container)${C_OFF}" \
    "Remote API  ${C_DIM}(OpenAI-compatible embeddings endpoint, e.g. a TEI server)${C_OFF}" \
    "Local GPU   ${C_DIM}(NVIDIA + CUDA PyTorch — seconds per page, needs build from source)${C_OFF}" \
    "Skip        ${C_DIM}(leave whatever .env already says)${C_OFF}"

EMBED_SUMMARY='local (CPU, in-container)'
EMBED_DEVICE='cpu'
USE_GPU=0

case "$REPLY_CHOICE" in
    1)
        # Local CPU
        set_env HDP_EMBEDDING_PROVIDER local
        set_env HDP_EMBEDDING_BASE_URL ""
        set_env HDP_EMBEDDING_API_KEY ""
        set_env HAYSTACK_DEVICE cpu
        [ -n "$(get_env HDP_EMBEDDING_MODEL)" ] \
            || set_env HDP_EMBEDDING_MODEL "mixedbread-ai/deepset-mxbai-embed-de-large-v1"
        [ -n "$(get_env HDP_EMBEDDING_DIM)" ] || set_env HDP_EMBEDDING_DIM 1024
        ok "Local embeddings on CPU — nothing else to configure."
        note "Expect 1–3 min per wiki page at ingestion time."
        ;;
    2)
        # Remote API
        set_env HDP_EMBEDDING_PROVIDER remote
        set_env HAYSTACK_DEVICE cpu
        ask "Embeddings base URL" "$(get_env HDP_EMBEDDING_BASE_URL)"
        [ -n "$REPLY_VALUE" ] || die "a base URL is required for the remote embedding provider"
        set_env HDP_EMBEDDING_BASE_URL "$REPLY_VALUE"
        EMBED_SUMMARY="remote — $REPLY_VALUE"

        ask "Embedding model" "$(get_env HDP_EMBEDDING_MODEL)"
        set_env HDP_EMBEDDING_MODEL "${REPLY_VALUE:-mixedbread-ai/deepset-mxbai-embed-de-large-v1}"

        while :; do
            ask "Embedding dimensions" "$(get_env HDP_EMBEDDING_DIM)"
            case "${REPLY_VALUE:-1024}" in
                ''|*[!0-9]*) warn "dimensions must be a number" ;;
                *) set_env HDP_EMBEDDING_DIM "${REPLY_VALUE:-1024}"; break ;;
            esac
        done

        ask_secret "Embeddings API key (hidden — press Enter if the endpoint needs none)"
        [ -n "$REPLY_VALUE" ] && set_env HDP_EMBEDDING_API_KEY "$REPLY_VALUE"
        warn "The dimension must match the index. Changing it on an existing install"
        note "  means a full re-ingestion, not an incremental one."
        ;;
    3)
        # Local GPU — CUDA PyTorch variant, needs nvidia-container-toolkit
        GPU_PROCEED=1
        if [ "$GPU_DETECTED" -eq 1 ]; then
            ok "Using the detected NVIDIA GPU${GPU_NAMES:+: $GPU_NAMES}"
        else
            warn "No NVIDIA GPU was detected by the installer."
            note "  This is common inside containers. Proceed only if the Docker host"
            note "  has a GPU and the NVIDIA Container Toolkit installed."
            confirm "Proceed with GPU mode anyway?" n || GPU_PROCEED=0
        fi

        if [ "$GPU_PROCEED" -eq 1 ]; then
            USE_GPU=1
            set_env HDP_EMBEDDING_PROVIDER local
            set_env HDP_EMBEDDING_BASE_URL ""
            set_env HDP_EMBEDDING_API_KEY ""
            set_env HAYSTACK_DEVICE gpu
            EMBED_DEVICE='gpu (NVIDIA)'
            EMBED_SUMMARY='local (GPU, in-container)'
            [ -n "$(get_env HDP_EMBEDDING_MODEL)" ] \
                || set_env HDP_EMBEDDING_MODEL "mixedbread-ai/deepset-mxbai-embed-de-large-v1"
            [ -n "$(get_env HDP_EMBEDDING_DIM)" ] || set_env HDP_EMBEDDING_DIM 1024
            printf '\n'
            ok "HAYSTACK_DEVICE=gpu — the CUDA PyTorch variant will be built (~8 GB image)."
            warn "GPU mode requires building from source (pre-built images are CPU-only)."
            warn "Install the NVIDIA Container Toolkit on the host first:"
            note "    sudo apt-get install -y nvidia-container-toolkit"
            note "    sudo nvidia-ctk runtime configure --runtime=docker && sudo systemctl restart docker"
            note "  Verify: docker run --rm --gpus all nvidia/cuda:12.4.0-base-ubuntu22.04 nvidia-smi"
        else
            # User declined GPU — fall back to CPU
            set_env HDP_EMBEDDING_PROVIDER local
            set_env HDP_EMBEDDING_BASE_URL ""
            set_env HDP_EMBEDDING_API_KEY ""
            set_env HAYSTACK_DEVICE cpu
            [ -n "$(get_env HDP_EMBEDDING_MODEL)" ] \
                || set_env HDP_EMBEDDING_MODEL "mixedbread-ai/deepset-mxbai-embed-de-large-v1"
            [ -n "$(get_env HDP_EMBEDDING_DIM)" ] || set_env HDP_EMBEDDING_DIM 1024
            EMBED_SUMMARY='local (CPU, fell back from GPU)'
            ok "Falling back to local CPU embeddings."
            note "Expect 1–3 min per wiki page at ingestion time."
        fi
        ;;
    *)
        # Skip — leave everything as-is in .env
        EMBED_SUMMARY="unchanged — $(get_env HDP_EMBEDDING_PROVIDER)"
        DEVICE_IN_ENV="$(get_env HAYSTACK_DEVICE)"
        if [ "$DEVICE_IN_ENV" = "gpu" ]; then
            EMBED_DEVICE='gpu (NVIDIA)'
            USE_GPU=1
        fi
        ok "Embedding settings left as they are."
        ;;
esac

# ─── 4e. Passwords ──────────────────────────────────────────────────
step "8/8  Passwords"

info "Four passwords are needed: MariaDB root, MariaDB user, wiki Admin, OpenSearch."
note "OpenSearch additionally runs a zxcvbn strength check — leetspeak like"
note "'Adm1nPassw0rd!' is rejected despite meeting every stated character rule."
printf '\n'

# The OpenSearch shape (Hdp-<hex>-26!) satisfies all four character classes and
# has enough entropy in the hex block to clear zxcvbn, which the documented
# examples do not.
gen_password()    { openssl rand -hex 16; }
gen_os_password() { printf 'Hdp-%s-26!' "$(openssl rand -hex 4)"; }

PW_DB_ROOT="$(get_env HDP_DB_ROOT_PASSWORD)"
PW_DB="$(get_env HDP_DB_PASSWORD)"
PW_ADMIN="$(get_env HDP_ADMIN_PASSWORD)"
PW_OS="$(get_env HDP_OPENSEARCH_PASSWORD)"

GENERATED=0
if confirm "Generate all four automatically with openssl?" y; then
    PW_DB_ROOT="$(gen_password)"
    PW_DB="$(gen_password)"
    PW_ADMIN="$(gen_password)"
    PW_OS="$(gen_os_password)"
    GENERATED=1
else
    printf '\n'
    note "Press Enter at any prompt to keep the value already in .env."

    ask_secret "MariaDB root password (hidden)"
    [ -n "$REPLY_VALUE" ] && PW_DB_ROOT="$REPLY_VALUE"

    ask_secret "MariaDB bluespice user password (hidden)"
    [ -n "$REPLY_VALUE" ] && PW_DB="$REPLY_VALUE"

    while :; do
        ask_secret "Wiki Admin password (hidden, 10+ characters)"
        [ -z "$REPLY_VALUE" ] && break
        if [ "${#REPLY_VALUE}" -lt 10 ]; then
            warn "MediaWiki requires at least 10 characters."
            continue
        fi
        PW_ADMIN="$REPLY_VALUE"
        break
    done

    while :; do
        ask_secret "OpenSearch admin password (hidden, 8+ chars, upper+lower+digit+special)"
        [ -z "$REPLY_VALUE" ] && break
        if [ "${#REPLY_VALUE}" -lt 8 ] \
            || [ -z "$(printf '%s' "$REPLY_VALUE" | tr -cd 'A-Z')" ] \
            || [ -z "$(printf '%s' "$REPLY_VALUE" | tr -cd 'a-z')" ] \
            || [ -z "$(printf '%s' "$REPLY_VALUE" | tr -cd '0-9')" ] \
            || [ -z "$(printf '%s' "$REPLY_VALUE" | tr -cd '[:punct:]')" ]; then
            warn "needs 8+ characters with an uppercase, a lowercase, a digit and a special character"
            continue
        fi
        PW_OS="$REPLY_VALUE"
        break
    done
fi

for _pw_name in PW_DB_ROOT PW_DB PW_ADMIN PW_OS; do
    [ -n "${!_pw_name}" ] || die "no value for $_pw_name — re-run and let the wizard generate the passwords."
    # Compose expands $VAR inside .env values, so a literal dollar in a password
    # reaches the container as something shorter than what was typed — and the
    # symptom is an authentication failure days later, not an error now. The
    # generated passwords never contain one; a hand-typed password can.
    case "${!_pw_name}" in
        *'$'*) warn "$_pw_name contains a '\$' — docker compose will try to expand it."
               note "  Escape it as \$\$ in .env, or choose a password without one." ;;
    esac
done

set_env HDP_DB_ROOT_PASSWORD "$PW_DB_ROOT"
set_env HDP_DB_PASSWORD "$PW_DB"
set_env HDP_ADMIN_PASSWORD "$PW_ADMIN"
set_env HDP_OPENSEARCH_PASSWORD "$PW_OS"

if [ "$GENERATED" -eq 1 ]; then
    printf '\n'
    warn "Written to .env AND printed once here. Save them now:"
    printf '\n'
    printf '    %-26s %s\n' "MariaDB root"      "$PW_DB_ROOT"
    printf '    %-26s %s\n' "MariaDB bluespice" "$PW_DB"
    printf '    %s%-26s %s%s\n' "$C_BLD" "Wiki Admin (login!)" "$PW_ADMIN" "$C_OFF"
    printf '    %-26s %s\n' "OpenSearch admin"  "$PW_OS"
    printf '\n'
else
    ok "Passwords written to .env"
fi

# ─── Review ─────────────────────────────────────────────────────────
step "Review"

mask() {
    if [ -n "$1" ]; then printf '•••••••• (set)'; else printf 'not set'; fi
}

printf '  %-22s %s\n' "Repository"        "$REPO_ROOT"
printf '  %-22s %s\n' "Config file"       "$ENV_FILE"
printf '  %-22s %s\n' "Secrets backend"   "$SECRETS_BACKEND"
printf '  %s%s%s\n' "$C_DIM" "${RULE:0:56}" "$C_OFF"
printf '  %-22s %s\n' "Site name"         "$MW_SITENAME_VAL"
printf '  %-22s %s\n' "Language"          "$MW_LANG_VAL"
printf '  %-22s %s\n' "Server URL"        "$MW_SERVER_VAL"
printf '  %-22s %s\n' "Host port"         "$MW_PORT_VAL"
printf '  %s%s%s\n' "$C_DIM" "${RULE:0:56}" "$C_OFF"
printf '  %-22s %s\n' "LLM provider"      "$LLM_PROVIDER"
printf '  %-22s %s\n' "LLM endpoint"      "$LLM_BASE_URL"
printf '  %-22s %s\n' "LLM model"         "$LLM_MODEL_VAL"
printf '  %-22s %s\n' "LLM API key"       "$LLM_KEY_STATE"
printf '  %-22s %s\n' "Embeddings"        "$EMBED_SUMMARY"
printf '  %-22s %s\n' "Inference device"  "$EMBED_DEVICE"
printf '  %s%s%s\n' "$C_DIM" "${RULE:0:56}" "$C_OFF"
printf '  %-22s %s\n' "MariaDB root pw"   "$(mask "$PW_DB_ROOT")"
printf '  %-22s %s\n' "MariaDB user pw"   "$(mask "$PW_DB")"
printf '  %-22s %s\n' "Wiki Admin pw"     "$(mask "$PW_ADMIN")"
printf '  %-22s %s\n' "OpenSearch pw"     "$(mask "$PW_OS")"
printf '\n'
note "Login after setup:  user 'Admin' with the wiki Admin password above."
printf '\n'

if ! confirm "Configuration looks right?" y; then
    printf '\n'
    info "Nothing else was changed. Edit $ENV_FILE by hand, or re-run ./install.sh."
    [ -f "$ENV_FILE.bak" ] && note "Your previous configuration is still in .env.bak"
    exit 0
fi

# ─── Start ──────────────────────────────────────────────────────────
step "Starting the stack"

# .env is complete and the operator has approved it, so the interrupt handler
# has nothing left to protect — and from here it would be actively wrong:
# Ctrl-C during `docker compose up` signals the whole foreground group, so the
# handler would revert the .env of a stack that is already half up.
trap - INT TERM

# The default start command, and the one printed under "Next steps" when the
# stack is not started from here. GPU adds one override to whichever path the
# operator picks — it composes with both the source build and the published
# images, though only the source build produces an image that can use the GPU.
COMPOSE_ARGS=(compose)
BUILD_ARGS=(compose)
if [ "$USE_GPU" -eq 1 ]; then
    COMPOSE_ARGS=(compose -f docker-compose.yml -f docker-compose.gpu.yml)
    BUILD_ARGS=("${COMPOSE_ARGS[@]}")
fi
STARTED=0

if [ "$DOCKER_READY" -eq 0 ]; then
    warn "Docker is not running, so the stack cannot be started from here."
else
    ask_choice "How should the three custom images be obtained?" 1 \
        "Pull pre-built images  ${C_DIM}(GHCR, ~5 GB download, no build)${C_OFF}" \
        "Build from source      ${C_DIM}(~5 minutes, picks up local changes)${C_OFF}" \
        "Do not start now       ${C_DIM}(just write .env)${C_OFF}"

    case "$REPLY_CHOICE" in
        1)
            COMPOSE_ARGS=(compose -f docker-compose.yml -f docker-compose.prod.yml)
            # Deliberately NOT adding the GPU override here. It cannot help —
            # the published images carry CPU-only torch — and layering it on
            # prod.yml's `build: !reset null` re-creates a build: key with no
            # dockerfile, so `up` would try to build ./Dockerfile and fail. See
            # docker-compose.gpu.yml. Falling back to a working CPU stack beats
            # both a broken build and a GPU reservation that does nothing.
            if [ "$USE_GPU" -eq 1 ]; then
                USE_GPU=0
                set_env HAYSTACK_DEVICE cpu
                warn "The published images are CPU-only builds, so GPU inference is off for this run."
                note "  HAYSTACK_DEVICE reset to cpu, keeping .env honest about what is running."
                note "  For the GPU, re-run ./install.sh and choose 'Build from source'."
            fi
            if [ "$COMPOSE_HAS_RESET" -eq 0 ]; then
                warn "compose < 2.24: pulling explicitly first, as the override cannot drop build: keys."
                docker "${COMPOSE_ARGS[@]}" pull || die "docker compose pull failed"
            fi
            info "docker ${COMPOSE_ARGS[*]} up -d"
            printf '\n'
            docker "${COMPOSE_ARGS[@]}" up -d || die "docker compose up failed — see the output above."
            STARTED=1
            ;;
        2)
            COMPOSE_ARGS=("${BUILD_ARGS[@]}")
            info "docker ${COMPOSE_ARGS[*]} up -d --build"
            printf '\n'
            docker "${COMPOSE_ARGS[@]}" up -d --build \
                || die "docker compose up --build failed — see the output above."
            STARTED=1
            ;;
        *)
            info "Skipped. Start it later with the commands below."
            ;;
    esac
fi

# ─── Next steps ─────────────────────────────────────────────────────
step "Next steps"

CC="docker ${COMPOSE_ARGS[*]}"

# The list is one item longer when the stack was not started here, so the
# numbers are counted rather than written out — a hardcoded "1." appearing
# twice is exactly the kind of detail a reader stops trusting the rest over.
N=0
num() { N=$((N + 1)); printf '  %s%d.%s ' "$C_BLD" "$N" "$C_OFF"; }

if [ "$STARTED" -eq 1 ]; then
    printf '\n'
    ok "Containers are up."
    printf '\n'
else
    num; printf 'Start the stack\n\n'
    printf '       cd %s\n' "$REPO_ROOT"
    if [ "$USE_GPU" -eq 1 ]; then
        # --build is not optional on the GPU path: the CUDA image has to be
        # built locally, since the published ones are CPU-only.
        printf '       %s up -d --build\n\n' "$CC"
        printf '     %sThe -f docker-compose.gpu.yml override is what reserves the GPU;%s\n' "$C_DIM" "$C_OFF"
        printf '     %severy command below carries it for the same reason.%s\n\n' "$C_DIM" "$C_OFF"
    else
        printf '       %s up -d\n\n' "$CC"
    fi
fi

num; printf 'Wait until mariadb and opensearch report "healthy" (30–60s)\n\n'
printf '       %s ps\n\n' "$CC"

num; printf 'Run the first-boot setup — installs MediaWiki + ~130 BlueSpice\n'
printf '     extensions, creates the database schema and the Admin account.\n'
printf '     Takes several minutes and only needs to be done once.\n\n'
printf '       %s exec mediawiki bash /setup.sh\n\n' "$CC"

num; printf 'Open the wiki and log in as Admin\n\n'
printf '       %s\n\n' "$MW_SERVER_VAL/w/"
printf '     %sBlueSpice requires a login before any page is visible, including the%s\n' "$C_DIM" "$C_OFF"
printf '     %smain page. A privacy consent prompt on first login is expected.%s\n\n' "$C_DIM" "$C_OFF"

num; printf 'Index the wiki for the chatbot (it answers nothing until this runs)\n\n'
printf '       %s exec haystack python3 ingest_hdp_wiki.py --dry-run\n' "$CC"
printf '       %s exec haystack python3 ingest_hdp_wiki.py\n\n' "$CC"
if [ "$USE_GPU" -eq 1 ]; then
    printf '     %sOn the GPU expect seconds rather than minutes per page; ingestion is%s\n' "$C_DIM" "$C_OFF"
else
    printf '     %sWith local CPU embeddings expect 1–3 min per page; ingestion is%s\n' "$C_DIM" "$C_OFF"
fi
printf '     %sidempotent, so --missing-only resumes an interrupted run.%s\n\n' "$C_DIM" "$C_OFF"

printf '  %sDocs:%s  README-DOCKER.md · docs/embedding-providers.md · docs/dev/AGENTS.md\n' "$C_BLD" "$C_OFF"
printf '  %sLogs:%s  %s logs -f haystack\n\n' "$C_BLD" "$C_OFF" "$CC"

exec 3<&-
exit 0
