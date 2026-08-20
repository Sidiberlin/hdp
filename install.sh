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
# .env.example documents, writes the answers to .env, and then — this is the
# point of the whole thing — brings the stack up and runs first-boot setup, so
# that what you have when it exits is a wiki you can log into rather than a list
# of commands still to run. The one job deliberately left to the operator is
# indexing the wiki for the chatbot, which takes hours on a large wiki and wants
# to be started when it suits them.
#
# Nothing here is irreversible: an existing .env is copied to .env.bak before a
# single line is changed, and every question is pre-filled with the value that
# .env already holds — so re-running this on a configured install is a way to
# change one setting, not a way to lose the rest.
#
# ─── Questions it does not ask ──────────────────────────────────────
#
# Whether to pull the published images or build from source used to be a
# question and is now a decision: it tries the pull, and builds only if the
# registry cannot supply the images. The operator has no information the
# installer lacks at that point, and both wrong answers are expensive — a
# needless five-minute build, or a stack that will not start.
#
# ─── Why every read is from fd 3 ────────────────────────────────────
#
# Under `curl … | bash` the script *is* stdin. A bare `read` would consume the
# not-yet-executed remainder of this file and the installer would silently stop
# partway through. fd 3 is opened on /dev/tty once, at the top, and every
# prompt reads from it; with no controlling terminal the script refuses to run
# rather than guessing answers.
#
# Exit: 0 configured, and started + installed if asked
#       1 prerequisite missing, user abort, or first-boot setup did not finish
#         (the containers are up in that case — see the closing message)
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
# !reset tag to delete the inherited build: keys, and older compose fails to
# parse the file outright.
#
# That is not fatal here: start_stack() tries the pull and falls back to a
# source build, so an old-compose host still ends up with a working wiki — it
# just spends five minutes building images that were sitting in the registry.
# Recorded so the fallback can say which of the two reasons it fired for,
# instead of leaving the operator to guess whether the registry was down.
COMPOSE_HAS_RESET=1
if [ -n "$COMPOSE_VERSION" ]; then
    if [ "$(printf '2.24.0\n%s\n' "$COMPOSE_VERSION" | sort -V | head -1)" != "2.24.0" ]; then
        COMPOSE_HAS_RESET=0
        warn "compose v$COMPOSE_VERSION is older than 2.24 — the published-image path"
        note "  needs 2.24's !reset tag, so this install will build from source."
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
while :; do
    ask "Server URL" "http://localhost:$MW_PORT_VAL"
    MW_SERVER_VAL="$REPLY_VALUE"
    # MediaWiki's $wgServer requires an explicit http:// or https:// prefix.
    # Without it, maintenance/install.php writes a value that breaks form
    # submissions (editing, login) — every POST is rejected because the
    # action URL is malformed. The operator sees the raw hostname in the
    # error, which is not obviously a missing-protocol problem.
    case "$MW_SERVER_VAL" in
        http://*|https://*) ;;
        *) warn "the URL must start with http:// or https://"; continue ;;
    esac
    # The port matters just as much: a URL without one, accepted beside a
    # host port other than 80, makes $wgServer build every canonical
    # redirect on port 80 — logins die with connection-refused while the
    # rest of the wiki keeps working. The missing-port sibling of the
    # protocol bug above.
    host_part="${MW_SERVER_VAL#http://}"
    host_part="${host_part#https://}"
    host_part="${host_part%%/*}"
    case "$host_part" in
        *:*)
            # An explicit port. One that differs from the host port can be
            # exactly right (a reverse proxy on 443) — never block it, note
            # it. NB: a bracketless IPv6 literal lands here too; harmless,
            # it reads as explicitly ported. The .env.example URLs are
            # plain hostnames.
            url_port="${host_part##*:}"
            if [ "$url_port" != "$MW_PORT_VAL" ]; then
                note "URL port :$url_port differs from host port $MW_PORT_VAL — fine behind a reverse proxy."
            fi
            break ;;
        *)
            if [ "$MW_PORT_VAL" != "80" ]; then
                warn "without an explicit port, MediaWiki's links and redirects point at port 80."
                if confirm "Append :$MW_PORT_VAL to the Server URL?" y; then
                    # Rebuild at the host boundary, not the string end, so
                    # http://host/wiki becomes http://host:PORT/wiki.
                    path_part="${MW_SERVER_VAL#http://}"
                    path_part="${path_part#https://}"
                    path_part="${path_part#"$host_part"}"
                    case "$MW_SERVER_VAL" in
                        https://*) MW_SERVER_VAL="https://$host_part:$MW_PORT_VAL$path_part" ;;
                        *)         MW_SERVER_VAL="http://$host_part:$MW_PORT_VAL$path_part" ;;
                    esac
                    break
                fi
                continue    # declined — re-prompt, never silently coerce
            fi
            break ;;        # port-less beside host port 80: correct as typed
    esac
done
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

# ─── Which PyTorch CUDA build this host's driver can run ────────────
# `pip install torch` with no index URL takes whatever CUDA build PyPI
# currently defaults to — cu124 today. NVIDIA drivers are backward compatible
# but not forward compatible: a driver whose ceiling is CUDA 12.0 loads a cu118
# torch happily and dies on a cu124 one with "CUDA driver version is
# insufficient for CUDA runtime version". That failure arrives at model load,
# inside the container, minutes after an install that looked like it worked, so
# it is worth two seconds of nvidia-smi here.
#
# nvidia-smi's header prints the highest CUDA version the *driver* supports,
# which is the number to compare against — not the CUDA toolkit that may or may
# not be installed alongside it.
#
# The cu124 threshold is 12.4 and not 12.0: cu124 wheels want a 12.4 driver,
# while cu118 wheels run on anything from 11.8 up, every 12.x driver included.
# A 12.0 driver therefore takes cu118 — the closest build below it, not the
# nearest 12.x one.
CUDA_MIN_CU124='12.4'
CUDA_MIN_CU118='11.8'

CUDA_MAX=''          # highest CUDA the host driver supports, e.g. 12.4
CUDA_WHEEL_TAG=''    # cu124 | cu118 | '' when no supported build fits
CUDA_UNKNOWN=0       # nvidia-smi told us nothing parseable
CUDA_TOO_OLD=0       # driver predates every PyTorch GPU build available
CUDA_VERIFY_TAG=''   # nvidia/cuda image tag this driver can actually run

# ver_ge <a> <b> — true when version a is at least version b.
ver_ge() {
    [ "$1" = "$2" ] && return 0
    [ "$(printf '%s\n%s\n' "$1" "$2" | sort -V | tail -1)" = "$1" ]
}

if [ "$GPU_DETECTED" -eq 1 ] && have nvidia-smi; then
    # Two spellings of one field: the header table is what every normal driver
    # prints, `-q` covers the packages that ship nvidia-smi without it. sed and
    # not grep -oP — -P is a GNU extension and this is not a line worth losing
    # on a host whose grep lacks it.
    CUDA_MAX="$(nvidia-smi 2>/dev/null \
        | sed -n 's/.*CUDA Version: *\([0-9][0-9.]*\).*/\1/p' | head -1)"
    [ -n "$CUDA_MAX" ] || CUDA_MAX="$(nvidia-smi -q 2>/dev/null \
        | sed -n 's/.*CUDA Version *: *\([0-9][0-9.]*\).*/\1/p' | head -1)"
fi

# Unparseable is not the same as too old, and the two get opposite treatment:
# an unknown version defaults to cu124 and says so, because refusing the GPU on
# a host that may well support it would be the more expensive mistake.
case "$CUDA_MAX" in
    ''|*[!0-9.]*)
        CUDA_MAX=''; CUDA_UNKNOWN=1; CUDA_WHEEL_TAG='cu124' ;;
    *)
        if ver_ge "$CUDA_MAX" "$CUDA_MIN_CU124"; then
            CUDA_WHEEL_TAG='cu124'
        elif ver_ge "$CUDA_MAX" "$CUDA_MIN_CU118"; then
            CUDA_WHEEL_TAG='cu118'
        else
            CUDA_TOO_OLD=1; CUDA_WHEEL_TAG=''
        fi ;;
esac

# The image the toolkit-verification command below uses. It must be one this
# driver can run: nvidia/cuda:12.4.0-base on a 12.0 driver fails with the very
# error this detection exists to avoid, and an operator debugging *that* would
# reasonably conclude their container toolkit is broken when it is fine.
case "$CUDA_WHEEL_TAG" in
    cu118) CUDA_VERIFY_TAG='11.8.0-base-ubuntu22.04' ;;
    *)     CUDA_VERIFY_TAG='12.4.0-base-ubuntu22.04' ;;
esac

if [ "$GPU_DETECTED" -eq 1 ]; then
    ok "NVIDIA GPU detected${GPU_NAMES:+: $GPU_NAMES}"
    if [ "$CUDA_TOO_OLD" -eq 1 ]; then
        note "  Driver supports CUDA $CUDA_MAX — older than every PyTorch GPU build"
        note "  (the oldest, cu118, needs CUDA $CUDA_MIN_CU118)"
    elif [ "$CUDA_UNKNOWN" -eq 1 ]; then
        note "  Driver CUDA version could not be read from nvidia-smi"
    else
        note "  Driver supports CUDA $CUDA_MAX — using PyTorch $CUDA_WHEEL_TAG build"
    fi
fi

# The menu changes based on whether a GPU is visible. When detected, "Local GPU"
# is the default (it's why you'd run on a GPU box). When not detected, GPU is
# still listed — the installer may be running inside a container that can't see
# the host's GPU, and the user knows their hardware.
#
# A GPU whose driver is too old for every PyTorch build is the one case where a
# GPU is present and GPU is still not the default: pressing Enter would pick an
# option this script is about to refuse, which is a bad default no matter how
# good the hardware is.
if [ "$GPU_DETECTED" -eq 1 ] && [ "$CUDA_TOO_OLD" -eq 0 ]; then
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
# Set when the driver needs a PyTorch build the published -gpu image does not
# carry. release.yml publishes exactly one GPU image and it is cu124, so a
# cu118 host has nothing to pull that would work and must build from source.
GPU_FORCE_BUILD=0

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
        GPU_FELL_BACK_REASON='fell back from GPU'
        # The too-old check comes first, ahead of "using the detected GPU": on
        # this host there is a GPU and it still cannot be used, and announcing
        # it before refusing it reads like a bug.
        #
        # A driver below CUDA 11.8 runs no PyTorch GPU wheel there is to
        # install, so the fallback is automatic rather than a question:
        # proceeding produces an image that builds, starts, passes its
        # healthcheck and then fails on the first embedding. The operator's real
        # choice is "update the driver or stay on CPU", and that is not one this
        # installer can make for them mid-run.
        if [ "$CUDA_TOO_OLD" -eq 1 ]; then
            GPU_PROCEED=0
            GPU_FELL_BACK_REASON="driver supports only CUDA $CUDA_MAX"
            warn "This host's NVIDIA driver supports only CUDA $CUDA_MAX."
            note "  The oldest PyTorch GPU build available is cu118, which needs a driver"
            note "  supporting CUDA $CUDA_MIN_CU118 — so GPU mode here would fail at model load"
            note "  with \"CUDA driver version is insufficient for CUDA runtime version\"."
            note "  Update the NVIDIA driver and re-run this installer to use the GPU."
            printf '\n'
        elif [ "$GPU_DETECTED" -eq 1 ]; then
            ok "Using the detected NVIDIA GPU${GPU_NAMES:+: $GPU_NAMES}"
        else
            warn "No NVIDIA GPU was detected by the installer."
            note "  This is common inside containers. Proceed only if the Docker host"
            note "  has a GPU and the NVIDIA Container Toolkit installed."
            confirm "Proceed with GPU mode anyway?" n || GPU_PROCEED=0
        fi

        if [ "$GPU_PROCEED" -eq 1 ]; then
            USE_GPU=1
            [ "$CUDA_WHEEL_TAG" = 'cu124' ] || GPU_FORCE_BUILD=1
            set_env HDP_EMBEDDING_PROVIDER local
            set_env HDP_EMBEDDING_BASE_URL ""
            set_env HDP_EMBEDDING_API_KEY ""
            set_env HAYSTACK_DEVICE gpu
            set_env HAYSTACK_CUDA_VERSION "$CUDA_WHEEL_TAG"
            EMBED_DEVICE="gpu (NVIDIA, $CUDA_WHEEL_TAG)"
            EMBED_SUMMARY='local (GPU, in-container)'
            [ -n "$(get_env HDP_EMBEDDING_MODEL)" ] \
                || set_env HDP_EMBEDDING_MODEL "mixedbread-ai/deepset-mxbai-embed-de-large-v1"
            [ -n "$(get_env HDP_EMBEDDING_DIM)" ] || set_env HDP_EMBEDDING_DIM 1024
            printf '\n'
            ok "HAYSTACK_DEVICE=gpu — the CUDA PyTorch variant (~8 GB image)."
            if [ "$CUDA_UNKNOWN" -eq 1 ]; then
                warn "Could not determine the CUDA driver version. Defaulting to cu124."
                note "  If the container reports \"CUDA driver version insufficient\", set"
                note "  HAYSTACK_CUDA_VERSION=cu118 in .env and rebuild:"
                note "    docker compose -f docker-compose.yml -f docker-compose.gpu.yml up -d --build"
            else
                ok "HAYSTACK_CUDA_VERSION=$CUDA_WHEEL_TAG — matched to a driver that supports CUDA $CUDA_MAX."
            fi
            if [ "$GPU_FORCE_BUILD" -eq 1 ]; then
                warn "The pre-built GPU image carries CUDA 12.4 PyTorch, which this driver"
                note "  cannot run. Building from source with the $CUDA_WHEEL_TAG wheels instead —"
                note "  one slower install, and the only build that will actually start."
            else
                note "Available both pre-built (ghcr.io/…/hdp-haystack:<tag>-gpu) and from source."
            fi
            warn "Install the NVIDIA Container Toolkit on the host first:"
            note "    sudo apt-get install -y nvidia-container-toolkit"
            note "    sudo nvidia-ctk runtime configure --runtime=docker && sudo systemctl restart docker"
            # The verification image has to be one this driver can run, or the
            # check fails for a reason that has nothing to do with the toolkit
            # it is meant to be testing — which is exactly the confusion this
            # whole detection step exists to prevent.
            note "  Verify: docker run --rm --gpus all nvidia/cuda:${CUDA_VERIFY_TAG} nvidia-smi"
        else
            # User declined GPU — fall back to CPU
            set_env HDP_EMBEDDING_PROVIDER local
            set_env HDP_EMBEDDING_BASE_URL ""
            set_env HDP_EMBEDDING_API_KEY ""
            set_env HAYSTACK_DEVICE cpu
            [ -n "$(get_env HDP_EMBEDDING_MODEL)" ] \
                || set_env HDP_EMBEDDING_MODEL "mixedbread-ai/deepset-mxbai-embed-de-large-v1"
            [ -n "$(get_env HDP_EMBEDDING_DIM)" ] || set_env HDP_EMBEDDING_DIM 1024
            EMBED_SUMMARY="local (CPU, $GPU_FELL_BACK_REASON)"
            ok "Falling back to local CPU embeddings."
            note "Expect 1–3 min per wiki page at ingestion time."
        fi
        ;;
    *)
        # Skip — leave everything as-is in .env
        EMBED_SUMMARY="unchanged — $(get_env HDP_EMBEDDING_PROVIDER)"
        DEVICE_IN_ENV="$(get_env HAYSTACK_DEVICE)"
        if [ "$DEVICE_IN_ENV" = "gpu" ]; then
            # Take the wheel choice from .env too, not from this host's driver:
            # "skip" means "keep what is configured", and an existing cu118
            # install still has to build from source rather than silently
            # switching to the pre-built cu124 image on the next start.
            CUDA_WHEEL_TAG="$(get_env HAYSTACK_CUDA_VERSION)"
            [ -n "$CUDA_WHEEL_TAG" ] || CUDA_WHEEL_TAG='cu124'
            [ "$CUDA_WHEEL_TAG" = 'cu124' ] || GPU_FORCE_BUILD=1
            EMBED_DEVICE="gpu (NVIDIA, $CUDA_WHEEL_TAG)"
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

# ─── From here on, the installer drives docker ──────────────────────
# .env is complete and the operator has approved it, so the interrupt handler
# has nothing left to protect — and from here it would be actively wrong:
# Ctrl-C during `docker compose up` signals the whole foreground group, so the
# handler would revert the .env of a stack that is already half up.
trap - INT TERM

# The two possible start commands. Which one is used is decided by trying the
# published images and falling back, not by asking — see start_stack().
#
# GPU is not an override stacked on top of either — it is a different file per
# path. Building from source uses docker-compose.gpu.yml (build args + device
# reservation); pulling uses docker-compose.prod-gpu.yml (the `-gpu` image +
# device reservation, no build key at all). The one combination that must never
# be assembled is prod.yml + gpu.yml, which parses and then fails at `up`; see
# the header of either file.
PULL_ARGS=(compose -f docker-compose.yml -f docker-compose.prod.yml)
BUILD_ARGS=(compose)
if [ "$USE_GPU" -eq 1 ]; then
    PULL_ARGS=(compose -f docker-compose.yml -f docker-compose.prod-gpu.yml)
    BUILD_ARGS=(compose -f docker-compose.yml -f docker-compose.gpu.yml)
fi
# The published images are the expected path, so they are also the commands
# printed if the stack is never started from here. start_stack() reassigns this
# if the pull does not work out.
COMPOSE_ARGS=("${PULL_ARGS[@]}")
UP_FLAGS=(up -d)

# …except on a GPU host whose driver cannot run the published image. There is
# one published -gpu image and it is a CUDA 12.4 build, so on a driver that
# needs cu118 the pull is not a shortcut, it is a 5 GB download of something
# that will not start. Decided here rather than in start_stack() so that the
# commands printed by the "not now" and "Docker is not running" exits are the
# ones that would actually work.
if [ "$GPU_FORCE_BUILD" -eq 1 ]; then
    COMPOSE_ARGS=("${BUILD_ARGS[@]}")
    UP_FLAGS=(up -d --build)
fi

# ─── Pre-downloading the models ─────────────────────────────────────
# The embedder (~600 MB) and the cross-encoder ranker (~450 MB) are pulled from
# HuggingFace by docker/haystack/entrypoint.sh the first time the container
# runs. That is correct but invisible: it happens behind a healthcheck whose
# start_period is 90s, so `docker compose ps` shows haystack "starting" for five
# to ten minutes with no indication that a download is what it is waiting on.
#
# Doing it here instead costs the same minutes but shows them, and it does it
# while the operator is still watching the installer rather than after they have
# walked away. The models land in the same `haystack_models` volume
# (/root/.cache/huggingface, see docker-compose.yml), so the entrypoint finds
# them cached and starts immediately.
#
# Via `docker compose run`, not a bare `docker run`: the models are only worth
# anything if they land in *this project's* volume, and the volume's real name
# is the compose project name plus `_haystack_models` — which is derived from
# the directory name and overridable, so nothing here can reconstruct it
# reliably. compose knows it. `--no-deps` keeps this from starting opensearch,
# and `--entrypoint python3` skips entrypoint.sh, which would otherwise wait for
# an OpenSearch that is not running.
#
# It also doubles as the probe start_stack() would otherwise have to make on its
# own: it pulls the haystack image, which is the largest of the three and the
# one most likely to be missing from the registry. COMPOSE_ARGS and not
# PULL_ARGS, because on the forced-build GPU path there is no image to pull —
# compose builds it here instead, which is a longer wait in the same place
# rather than a wasted download followed by a build.
PREDOWNLOAD_PY='
import os

from sentence_transformers import SentenceTransformer

models = [
    os.environ.get("HDP_EMBEDDING_MODEL") or "mixedbread-ai/deepset-mxbai-embed-de-large-v1",
    "PM-AI/bi-encoder_msmarco_bert-base_german",
]
for name in models:
    print("  fetching  " + name, flush=True)
    SentenceTransformer(name)
    print("  cached    " + name, flush=True)
print("OK")
'

# Answers "is there a local model to fetch at all?" — remote embeddings have no
# in-container embedder. The ranker is always local, but on its own it is not
# worth a prompt and the entrypoint already fetches it.
embeddings_are_local() { [ "$(get_env HDP_EMBEDDING_PROVIDER)" = "local" ]; }

predownload_models() {
    local model
    model="$(get_env HDP_EMBEDDING_MODEL)"
    [ -n "$model" ] || model='mixedbread-ai/deepset-mxbai-embed-de-large-v1'

    info "Two models run in-container and are downloaded on first start:"
    printf '       %s%s%s %s(embedder, ~600 MB)%s\n' \
        "$C_BLD" "$model" "$C_OFF" "$C_DIM" "$C_OFF"
    printf '       %sPM-AI/bi-encoder_msmarco_bert-base_german%s %s(ranker, ~450 MB)%s\n' \
        "$C_BLD" "$C_OFF" "$C_DIM" "$C_OFF"
    note "Fetching them now makes the first start a start, rather than five to ten"
    note "minutes of a healthcheck with nothing to show for itself."
    printf '\n'
    if ! confirm "Pre-download embedding models now? (saves time on first start)" y; then
        note "Skipped — they will download on first container start."
        return 0
    fi

    if [ "$GPU_FORCE_BUILD" -eq 1 ]; then
        printf '\n'
        note "This also builds the CUDA image ($CUDA_WHEEL_TAG wheels, ~8 GB) — it has to"
        note "exist before anything can run in it. Allow longer on a slow disk."
    fi

    printf '\n'
    info "docker ${COMPOSE_ARGS[*]} run --rm --no-deps -T --entrypoint python3 haystack …"
    printf '\n'
    # -T because stdin here is the installer's own stdin, which under
    # `curl … | bash` is the script itself: an attached compose run drains the
    # pipe bash is reading, and the installer dies silently after "Models
    # cached…". The < /dev/null redirect is the second belt — it also covers
    # any future docker-run fallback shape.
    if docker "${COMPOSE_ARGS[@]}" run --rm --no-deps -T --entrypoint python3 \
            haystack -c "$PREDOWNLOAD_PY" < /dev/null; then
        printf '\n'
        ok "Models cached in the haystack_models volume."
    else
        # Deliberately not fatal. A failure here costs the operator nothing but
        # the wait they would have had anyway — the entrypoint retries the exact
        # same download — so it must not stand between them and a running stack.
        printf '\n'
        warn "Pre-download did not complete. This is not fatal:"
        note "  the container downloads the same models on first start."
    fi
}

# ─── Bringing the stack up ──────────────────────────────────────────

# compose_state <service> — one word describing the container behind a service:
# its healthcheck's verdict where it has one, its container state otherwise, and
# `gone` when compose does not know about it at all.
compose_state() {
    local cid
    cid="$(docker "${COMPOSE_ARGS[@]}" ps -q "$1" 2>/dev/null | head -1)"
    [ -n "$cid" ] || { printf 'gone'; return 0; }
    docker inspect \
        -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' \
        "$cid" 2>/dev/null || printf 'gone'
}

# wait_ready <label> <timeout-seconds> <service>... — returns 0 once every named
# service reports healthy (or running, for the ones with no healthcheck).
#
# Polling the containers rather than sleeping a fixed 60s: on a warm host
# MariaDB is up in fifteen seconds and on a cold one with a cold image cache it
# can take three minutes, and a fixed sleep is wrong in both directions — it
# either wastes the operator's time or hands setup.sh a database that is not
# accepting connections yet, which fails in a way that reads like a bug.
wait_ready() {
    local label="$1" limit="$2"
    shift 2
    local services=("$@") waited=0 svc state pending
    printf '  %s' "$label"
    while :; do
        pending=''
        for svc in "${services[@]}"; do
            state="$(compose_state "$svc")"
            case "$state" in
                healthy|running) ;;
                *) pending="$pending $svc($state)" ;;
            esac
        done
        if [ -z "$pending" ]; then
            printf ' %s✓%s\n' "$C_GRN" "$C_OFF"
            return 0
        fi
        if [ "$waited" -ge "$limit" ]; then
            printf ' %s!%s\n' "$C_YEL" "$C_OFF"
            warn "after ${limit}s, still waiting on:$pending"
            return 1
        fi
        printf '.'
        sleep 3
        waited=$((waited + 3))
    done
}

# Pre-built images first, a source build only if the registry cannot supply
# them. This is deliberately not a question: an operator who has just answered
# eight of them does not have information the installer lacks here, and the
# wrong answer costs them five minutes or a stack that will not start. `pull` is
# the probe because it is also the work — a successful pull leaves exactly the
# images `up` is about to want. The exception is a GPU host whose driver cannot
# run the published CUDA 12.4 image; there the answer is known in advance and
# the probe is skipped.
start_stack() {
    if [ "$GPU_FORCE_BUILD" -eq 1 ]; then
        # The one case where the pull is not even attempted. There is a single
        # published GPU image and it is a CUDA 12.4 build, so on a driver that
        # needs cu118 the registry has nothing to probe for — and a successful
        # pull here would be worse than a failed one, buying a container that
        # starts and then cannot load a model.
        COMPOSE_ARGS=("${BUILD_ARGS[@]}")
        UP_FLAGS=(up -d --build)
        info "Building the haystack image from source — the published GPU image is a"
        note "  CUDA 12.4 build and this host's driver needs $CUDA_WHEEL_TAG."
        note "  The CUDA build is ~8 GB, so allow longer on a slow disk."
    else
        info "Fetching the published images…"
        printf '\n'
        if docker "${PULL_ARGS[@]}" pull; then
            COMPOSE_ARGS=("${PULL_ARGS[@]}")
            UP_FLAGS=(up -d)
            printf '\n'
            ok "Using the published images."
        else
            COMPOSE_ARGS=("${BUILD_ARGS[@]}")
            UP_FLAGS=(up -d --build)
            printf '\n'
            warn "The published images could not be pulled — building from source instead."
            note "  About five minutes, and the result is the same stack."
            if [ "$COMPOSE_HAS_RESET" -eq 0 ]; then
                note "  Expected here: compose v${COMPOSE_VERSION:-<2.24} cannot parse the override."
            fi
            if [ "$USE_GPU" -eq 1 ]; then
                note "  The CUDA build is ~8 GB, so allow longer on a slow disk."
            fi
        fi
    fi
    printf '\n'
    info "docker ${COMPOSE_ARGS[*]} ${UP_FLAGS[*]}"
    printf '\n'
    docker "${COMPOSE_ARGS[@]}" "${UP_FLAGS[@]}" \
        || die "docker compose up failed — see the output above."
}

# ─── Start ──────────────────────────────────────────────────────────
WIKI_URL="$MW_SERVER_VAL/w/"
SETUP_OK=1

if [ "$DOCKER_READY" -eq 0 ]; then
    step "Not starting — Docker is not running"

    warn "The Docker daemon did not respond, so nothing can be started from here."
    note "Start it (usually: sudo systemctl start docker), then:"
    printf '\n'
    printf '       cd %s && docker %s %s\n' "$REPO_ROOT" "${COMPOSE_ARGS[*]}" "${UP_FLAGS[*]}"
    printf '       docker %s exec mediawiki bash /setup.sh\n' "${COMPOSE_ARGS[*]}"
    printf '       docker %s exec haystack python3 ingest_hdp_wiki.py   %s# needed for grounded chatbot answers%s\n\n' \
        "${COMPOSE_ARGS[*]}" "$C_DIM" "$C_OFF"
    printf '  %sWiki:%s   %s   %slogin Admin / the password above%s\n' \
        "$C_BLD" "$C_OFF" "$WIKI_URL" "$C_DIM" "$C_OFF"
    printf '  %sDocs:%s   README-DOCKER.md\n\n' "$C_BLD" "$C_OFF"
    exec 3<&-
    exit 0
fi

# Before the start question on purpose. The download is the single longest thing
# the installer does, it is entirely independent of whether the stack comes up
# now, and an operator who says "not now" still keeps the cached models for
# whenever they do start it.
if embeddings_are_local; then
    step "Embedding models"
    predownload_models
fi

step "Services"

if ! confirm "Start the services now?" y; then
    printf '\n'
    ok "Nothing started. Everything is configured and waiting."
    printf '\n'
    printf '       cd %s\n' "$REPO_ROOT"
    printf '       docker %s %s\n' "${COMPOSE_ARGS[*]}" "${UP_FLAGS[*]}"
    printf '       docker %s exec mediawiki bash /setup.sh   %s# first boot, several minutes%s\n' \
        "${COMPOSE_ARGS[*]}" "$C_DIM" "$C_OFF"
    printf '       docker %s exec haystack python3 ingest_hdp_wiki.py   %s# chatbot index (needed for grounded answers)%s\n\n' \
        "${COMPOSE_ARGS[*]}" "$C_DIM" "$C_OFF"
    printf '  %sWiki:%s   %s   %slogin Admin / the password above%s\n' \
        "$C_BLD" "$C_OFF" "$WIKI_URL" "$C_DIM" "$C_OFF"
    printf '  %sDocs:%s   README-DOCKER.md\n\n' "$C_BLD" "$C_OFF"
    exec 3<&-
    exit 0
fi

printf '\n'
start_stack

printf '\n'
ok "Containers are up."
note "MariaDB and OpenSearch take 30–60s to report healthy. Waiting for them —"
note "nothing below can run until they do."
printf '\n'

# 300s, not 60: OpenSearch on a cold single-node cluster with a slow disk is the
# long pole, and failing here would abandon a stack that was merely slow. The
# timeout exists so this cannot hang forever, not as an expected duration.
if wait_ready "database and search  " 300 mariadb opensearch \
    && wait_ready "wiki container      " 180 mediawiki; then

    step "First-boot setup"

    info "Installing MediaWiki and ~130 BlueSpice extensions, creating the"
    info "database schema and the Admin account. Several minutes, once ever."
    printf '\n'
    info "docker ${COMPOSE_ARGS[*]} exec -T mediawiki bash /setup.sh"
    printf '\n'
    # -T because stdin here is the installer's own stdin, which under
    # `curl … | bash` is the script itself and is not a terminal. Without it
    # compose refuses with "the input device is not a TTY" and the whole
    # one-command promise dies on the last step. But -T only declines the
    # pseudo-TTY — it does not detach stdin, and an exec client still drains
    # the pipe bash is reading (found live: a pipe-fed install ended
    # silently right here — setup done, then exit 0 with no ready block).
    # The < /dev/null redirect is what keeps the script pipe alive, exactly
    # as at the pre-download run above.
    docker "${COMPOSE_ARGS[@]}" exec -T mediawiki bash /setup.sh < /dev/null \
        || SETUP_OK=0
else
    SETUP_OK=0
    printf '\n'
    warn "Services did not become healthy in time, so first-boot setup was not run."
fi

# ─── Done ───────────────────────────────────────────────────────────
if [ "$SETUP_OK" -eq 1 ]; then
    step "Your wiki is ready"

    printf '  %sWiki%s      %s%s%s\n' "$C_BLD" "$C_OFF" "$C_BLD$C_BLU" "$WIKI_URL" "$C_OFF"
    printf '  %sLogin%s     Admin  /  %s%s%s\n' "$C_BLD" "$C_OFF" "$C_BLD" "$PW_ADMIN" "$C_OFF"
    printf '\n'
    note "BlueSpice shows nothing before you log in, including the main page."
    note "A privacy consent prompt on first login is expected."
    printf '\n'
    # ─── Ingestion step ────────────────────────────────────────────
    # The chatbot returns ungrounded answers ("I did not provide any
    # documents") until the wiki pages are embedded into OpenSearch. Offer
    # to run it now rather than leaving it as a post-install manual step,
    # but make it optional — on a large wiki or CPU-only embeddings it can
    # take hours.
    printf '\n'
    printf '  %sOne thing left.%s The chatbot returns no grounded answers until the wiki is indexed.\n' \
        "$C_BLD" "$C_OFF"
    if [ "$USE_GPU" -eq 1 ]; then
        note "On the GPU expect seconds per page."
    else
        note "With local CPU embeddings expect 1–3 min per page."
    fi

    if confirm "Run wiki ingestion now?" n; then
        printf '\n'
        info "Indexing the wiki into OpenSearch. This runs in the foreground so you"
        info "can watch progress — Ctrl+C stops it, and --missing-only resumes later."
        printf '\n'
        info "docker ${COMPOSE_ARGS[*]} exec -T haystack python3 ingest_hdp_wiki.py"
        printf '\n'
        INGEST_OK=0
        # Same belt as the setup exec above: -T alone does not detach stdin,
        # and this is the last docker call of the run — a drained pipe here
        # would eat the closing block and still exit 0.
        docker "${COMPOSE_ARGS[@]}" exec -T haystack \
            python3 ingest_hdp_wiki.py < /dev/null || INGEST_OK=1
        if [ "$INGEST_OK" -ne 0 ]; then
            printf '\n'
            warn "Ingestion did not complete — see the output above. Re-run with:"
            printf '       docker %s exec haystack python3 ingest_hdp_wiki.py --missing-only\n\n' "${COMPOSE_ARGS[*]}"
        fi
    else
        printf '\n'
        note "Run ingestion when ready — the chatbot cannot provide grounded answers"
        note "without it:"
        printf '\n'
        printf '       docker %s exec haystack python3 ingest_hdp_wiki.py\n' "${COMPOSE_ARGS[*]}"
        printf '       %s# add --missing-only to resume an interrupted run%s\n' "$C_DIM" "$C_OFF"
    fi

    printf '\n'
    printf '  %sLogs:%s  docker %s logs -f haystack\n' "$C_BLD" "$C_OFF" "${COMPOSE_ARGS[*]}"
    printf '  %sDocs:%s  README-DOCKER.md · docs/embedding-providers.md\n\n' "$C_BLD" "$C_OFF"
    exec 3<&-
    exit 0
fi

# Setup did not complete. The containers are up, so this is a troubleshooting
# problem and not a lost install — say exactly that, and do not pretend the
# wiki is usable. Exit 1: something the installer set out to do did not happen,
# and a caller that checks the status should hear about it.
step "Services are up, but first-boot setup did not finish"

warn "The wiki is not installed yet. Nothing is lost — the containers are"
note "  running and setup.sh is safe to re-run once the cause is fixed."
printf '\n'
printf '  %sLook first at%s\n\n' "$C_BLD" "$C_OFF"
printf '       docker %s ps\n' "${COMPOSE_ARGS[*]}"
printf '       docker %s logs mariadb\n' "${COMPOSE_ARGS[*]}"
printf '       docker %s logs mediawiki\n\n' "${COMPOSE_ARGS[*]}"
printf '  %sThen re-run%s\n\n' "$C_BLD" "$C_OFF"
printf '       docker %s exec mediawiki bash /setup.sh\n\n' "${COMPOSE_ARGS[*]}"
printf '  %sWiki (once setup succeeds):%s %s   %slogin Admin / the password above%s\n' \
    "$C_BLD" "$C_OFF" "$WIKI_URL" "$C_DIM" "$C_OFF"
printf '  %sDocs:%s README-DOCKER.md\n\n' "$C_BLD" "$C_OFF"
exec 3<&-
exit 1
