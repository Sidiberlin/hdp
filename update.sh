#!/usr/bin/env bash
# ============================================================
# update.sh — move an existing HDP install to the latest release.
#
#   ./update.sh                      from inside the checkout
#   cd hdp && ./update.sh            after the one-line install
#   curl -fsSL .../update.sh | bash  pipe-fed, same as install.sh
#
# What it does NOT do: reconfigure. ./install.sh remains the only thing
# that writes configuration answers — this script only moves the tree
# forward and reports .env drift. No auto-update daemon, no --yes, no
# auto-rollback: every destructive step is named and confirmed by a human,
# and on failure this prints the rollback commands rather than running them.
#
# ─── What "update" means here ───────────────────────────────────────
#
# The default target is the latest *release tag* (the newest `v*` tag on the
# remote, release candidates and other pre-releases excluded), not the tip of
# `main`. HDP_UPDATE_REF=main opts back into tracking the branch;
# HDP_UPDATE_REF=<tag> pins one specific tag. See the usage text below.
#
# ─── Mechanics ───────────────────────────────────────────────────────
#
# `git fetch --depth 1` + `git reset --hard`, not `git pull` — deterministic
# on a vendored, --depth-1 tree that nobody is meant to be authoring in (see
# docs/dev/upgrade-runbook.md). HEAD stays attached to whatever branch it was
# on; the target tag is created locally so `git describe --tags` can name it
# afterwards. The previous tip is preserved at refs/hdp/pre-update before
# anything else happens, so a shallow fetch can never leave it unreferenced
# and gc-able.
#
# docker/setup.sh — not a bare update.php — is what runs against the running
# stack: it re-applies the Class-A patches composer clobbers, sweeps file
# ownership after the reset (git writes as root; FPM runs as www-data), and
# is the only thing that seeds a new release's content into an existing
# install through its versioned marker files.
#
# ─── Why every read is from fd 3 ────────────────────────────────────
#
# Same reason as install.sh: under `curl | bash` this script IS stdin, so a
# bare `read` would consume the remainder of the file. fd 3 is opened on
# /dev/tty once, at the top, and every prompt reads from it.
#
# ─── Why every docker run/exec carries -T and < /dev/null ──────────
#
# `-T` declines the pseudo-TTY but does NOT detach stdin — an attached
# `compose exec` still drains whatever bash is reading this script from
# under a pipe-fed invocation, silently truncating everything after it. The
# `< /dev/null` redirect is the actual guard (see tests/bats/install_pipe.bats
# and its update.sh sibling, tests/bats/update_pipe.bats). There are exactly
# two such sites in this script: the setup.sh exec and the mysqldump exec.
#
# Exit: 0 updated, or already current
#       1 the update ran and did not finish clean (stack is up; the rollback
#         block was printed — see it above this message)
#       2 refused to start (not a checkout, dirty tree, no tty, unreachable
#         remote, unknown option)
#       130 interrupted
# ============================================================
set -uo pipefail

CLONE_DIR="${HDP_CLONE_DIR:-hdp}"

# ─── Output — identical language to install.sh ─────────────────────
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

# refuse <message> — exit 2: refused to start, nothing touched.
refuse() {
    printf '\n  %sERROR%s %s\n\n' "$C_RED" "$C_OFF" "$1" >&2
    exec 3<&- 2>/dev/null || true
    exit 2
}

usage() {
    cat <<EOF
${C_BLD}update.sh${C_OFF} — move an existing HDP install to the latest release.

  ./update.sh                        move to the latest release tag (default)
  HDP_UPDATE_REF=main ./update.sh    follow the tip of main instead (opt-in)
  HDP_UPDATE_REF=v5.2.0 ./update.sh  pin one specific tag
  ./update.sh --check                show what would happen, change nothing
  ./update.sh --discard-local        skip the dirty-tree guard (the reset
                                      was going to discard those changes anyway)

Environment overrides:
  HDP_UPDATE_REF   release channel override (see above)
  HDP_CLONE_DIR    checkout to update when run from outside one (default: ./$CLONE_DIR)
  NO_COLOR         disable colour output

Every destructive step is named and confirmed before it runs. No
auto-update, no --yes, no auto-rollback: on failure this prints the exact
commands to go back, it does not run them.
EOF
}

CHECK_ONLY=0
DISCARD_LOCAL=0
while [ $# -gt 0 ]; do
    case "$1" in
        --help|-h)       usage; exit 0 ;;
        --check)         CHECK_ONLY=1 ;;
        --discard-local) DISCARD_LOCAL=1 ;;
        *) printf 'update.sh: unknown option %s (try --help)\n' "$1" >&2; exit 2 ;;
    esac
    shift
done

# ─── A terminal to ask questions on ─────────────────────────────────
# Skipped for --check: it prints the plan block and exits, with no prompt to
# answer. See install.sh's header for why every other path needs one.
if [ "$CHECK_ONLY" -eq 0 ]; then
    if ! { exec 3</dev/tty; } 2>/dev/null; then
        printf 'update.sh needs an interactive terminal and has none.\n' >&2
        printf 'Run it directly from a checkout instead:\n\n' >&2
        printf '  cd %s && ./update.sh\n\n' "$CLONE_DIR" >&2
        exit 2
    fi
fi

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

# ─── Locate the checkout ─────────────────────────────────────────────
step "update.sh"

SCRIPT_SRC="${BASH_SOURCE[0]:-}"
SCRIPT_DIR=''
if [ -n "$SCRIPT_SRC" ] && [ -f "$SCRIPT_SRC" ]; then
    SCRIPT_DIR="$(cd "$(dirname "$SCRIPT_SRC")" && pwd)"
fi

REPO_ROOT=''
NO_GIT_DIR=''
for d in "$SCRIPT_DIR" "$PWD" "$PWD/$CLONE_DIR"; do
    [ -n "$d" ] || continue
    [ -f "$d/docker-compose.yml" ] || continue
    if [ -d "$d/.git" ]; then
        REPO_ROOT="$(cd "$d" && pwd)"
        break
    else
        NO_GIT_DIR="$d"
    fi
done

if [ -z "$REPO_ROOT" ]; then
    if [ -n "$NO_GIT_DIR" ]; then
        refuse "$(printf '%s is an HDP checkout with no .git directory (a tarball install).\nupdate.sh needs git history to fetch and diff against — re-clone with git:\n\n  git clone <your fork URL> hdp-new\n  cp %s/.env hdp-new/.env\n  cd hdp-new && docker compose up -d' "$NO_GIT_DIR" "$NO_GIT_DIR")"
    fi
    refuse "$(printf 'No HDP checkout found here or in ./%s.\n\nRun update.sh one of two ways:\n\n  cd <your checkout> && ./update.sh\n  cd %s && ./update.sh          # after the one-line install' "$CLONE_DIR" "$CLONE_DIR")"
fi

cd "$REPO_ROOT" || refuse "cannot enter $REPO_ROOT"
ok "Checkout: $REPO_ROOT"

ENV_FILE="$REPO_ROOT/.env"
[ -f "$ENV_FILE" ] || refuse "$(printf 'No .env in %s — this install has never been configured.\nRun ./install.sh first.' "$REPO_ROOT")"

# get_env <key> — first uncommented assignment in .env, quotes stripped.
get_env() {
    local line
    line="$(grep -m1 "^$1=" "$ENV_FILE" 2>/dev/null || true)"
    line="${line#"$1"=}"
    line="${line%\"}"
    line="${line#\"}"
    printf '%s' "$line"
}

# ─── Dirty-tree guard (D2) ────────────────────────────────────────────
# git reset --hard is only a sane "identical to upstream" operation on a tree
# that has nothing of the operator's own in it. The one expected exception:
# docker/setup.sh permanently rewrites the tracked app/composer.lock on
# first boot (SSH->HTTPS rewrite, then strips the two private packages) and
# never restores it — verified by reading docker/setup.sh (see its "Step 1:
# Fix Composer" block); the committed form is safe to reset to because the
# runtime never reads composer.lock once vendor/ exists (it re-strips it on
# the next composer run regardless).
ALLOWED_DRIFT=(app/composer.lock)

if [ "$DISCARD_LOCAL" -eq 0 ]; then
    declare -a OFFENDING=()
    while IFS= read -r line; do
        [ -n "$line" ] || continue
        path="${line:3}"
        allowed=0
        for a in "${ALLOWED_DRIFT[@]}"; do
            [ "$path" = "$a" ] && { allowed=1; break; }
        done
        [ "$allowed" -eq 1 ] || OFFENDING+=("$line")
    done < <(git status --porcelain --untracked-files=no)

    if [ ${#OFFENDING[@]} -gt 0 ]; then
        printf '\n'
        warn "The working tree has changes update.sh cannot tell from upstream's:"
        printf '\n'
        for o in "${OFFENDING[@]}"; do
            printf '    %s\n' "$o"
        done
        printf '\n'
        info "Three ways out:"
        note "  git stash                    keep the changes, apply them back later"
        note "  git commit                   make them part of history"
        note "  ./update.sh --discard-local  the reset was going to discard them anyway"
        refuse "refusing to reset a dirty tree — see above."
    fi
fi

COMPOSER_LOCK_DIRTY=0
git status --porcelain --untracked-files=no -- app/composer.lock 2>/dev/null \
    | grep -q . && COMPOSER_LOCK_DIRTY=1

# ─── Protect the old commit before touching the network ─────────────
# A shallow fetch can leave the previous HEAD unreferenced and gc-able. This
# ref (overwritten each run; the previous value is still in the reflog) is
# what makes the rollback block's SHA recoverable no matter what the fetch
# below does.
OLD_SHA="$(git rev-parse HEAD)"
git update-ref refs/hdp/pre-update "$OLD_SHA"

CURRENT_BRANCH="$(git symbolic-ref --quiet --short HEAD 2>/dev/null || true)"
DETACHED=0
[ -z "$CURRENT_BRANCH" ] && DETACHED=1

REMOTE_URL="$(git remote get-url origin 2>/dev/null || true)"
[ -n "$REMOTE_URL" ] || refuse "no 'origin' remote is configured — nothing to update from."

# ─── Resolve the release channel (D3 / Q3) ───────────────────────────
UPDATE_REF="${HDP_UPDATE_REF:-}"
BRANCH_MODE=0
TAG=''
FETCH_REF=''

if [ "$UPDATE_REF" = "main" ]; then
    BRANCH_MODE=1
    FETCH_REF='main'
elif [ -n "$UPDATE_REF" ]; then
    TAG="$UPDATE_REF"
else
    LS_ERR="$(mktemp)"
    # --refs drops the peeled ^{} entries an annotated tag also advertises,
    # so no separate strip-and-dedupe pass is needed. Deny-list, not
    # allow-list, on purpose: a future *release* suffix (there will be one)
    # works the day it is coined; a future *pre-release* suffix is a one-line
    # addition here. release.yml is the source of truth for which is which.
    #
    # sort -V is not semver — measured on this tree, v5.1.9-rc1 outranks
    # every -QoL tag of the same base version because 'r' > 'Q' in ASCII — so
    # excluding -rc (and -alpha/-beta/-pre) is load-bearing, not cosmetic:
    # without it, the first release candidate tagged becomes everyone's
    # default update target. -QoL* is deliberately NOT excluded: those are
    # this fork's own repo-level releases (the tree, the installer, the
    # docs) even though they publish no images of their own — see the image
    # gap handling below.
    TAG_LIST="$(git ls-remote --tags --refs origin 'v*' 2>"$LS_ERR" \
        | awk '{print $2}' | sed 's#^refs/tags/##' \
        | grep -Eiv -- '-(rc|alpha|beta|pre)' \
        | sort -V || true)"
    if [ ! -s "$LS_ERR" ] || [ -n "$TAG_LIST" ]; then
        TAG="$(printf '%s\n' "$TAG_LIST" | tail -1)"
    fi
    if [ -z "$TAG" ]; then
        if [ -s "$LS_ERR" ] && [ -z "$TAG_LIST" ]; then
            ERRTXT="$(cat "$LS_ERR")"
            rm -f "$LS_ERR"
            refuse "$(printf 'could not reach %s:\n%s' "$REMOTE_URL" "$ERRTXT")"
        fi
        # No v* tag at all — a fork that has never tagged. Fall back to the
        # remote's default branch rather than exiting 2: HDP_REPO_URL forks
        # are the only reason this fallback exists.
        FETCH_REF="$(git ls-remote --symref origin HEAD 2>/dev/null \
            | awk '/^ref:/ { sub("refs/heads/", "", $2); print $2 }')"
        [ -n "$FETCH_REF" ] || FETCH_REF='main'
        BRANCH_MODE=1
        warn "No v* release tag exists on the remote — following $FETCH_REF instead."
    fi
    rm -f "$LS_ERR"
fi

TARGET_LABEL="$TAG"
[ "$BRANCH_MODE" -eq 1 ] && TARGET_LABEL="$FETCH_REF (branch)"

# ─── Fetch ────────────────────────────────────────────────────────────
FETCH_ERR="$(mktemp)"
if [ "$BRANCH_MODE" -eq 1 ]; then
    info "Channel: tip of $FETCH_REF"
    if ! git fetch --depth 1 origin "$FETCH_REF" 2>"$FETCH_ERR"; then
        ERRTXT="$(cat "$FETCH_ERR")"; rm -f "$FETCH_ERR"
        refuse "$(printf 'git fetch origin %s failed:\n%s' "$FETCH_REF" "$ERRTXT")"
    fi
    NEW_SHA="$(git rev-parse FETCH_HEAD)"
else
    info "Channel: latest release tag -> $TAG"
    if ! git fetch --depth 1 origin "+refs/tags/$TAG:refs/tags/$TAG" 2>"$FETCH_ERR"; then
        ERRTXT="$(cat "$FETCH_ERR")"; rm -f "$FETCH_ERR"
        refuse "$(printf 'git fetch of tag %s failed:\n%s' "$TAG" "$ERRTXT")"
    fi
    NEW_SHA="$(git rev-parse "refs/tags/$TAG^{commit}")"
fi
rm -f "$FETCH_ERR"

OLD_SHA_SHORT="$(git rev-parse --short "$OLD_SHA")"
NEW_SHA_SHORT="$(git rev-parse --short "$NEW_SHA")"
OLD_SUBJECT="$(git log -1 --format=%s "$OLD_SHA" 2>/dev/null || true)"
NEW_SUBJECT="$(git log -1 --format=%s "$NEW_SHA" 2>/dev/null || true)"

if [ "$OLD_SHA" = "$NEW_SHA" ]; then
    printf '\n'
    ok "Already up to date — $TARGET_LABEL is $OLD_SHA_SHORT."
    exec 3<&- 2>/dev/null || true
    exit 0
fi

# ─── Backwards-move guard (D1.9) ─────────────────────────────────────
# A maintainer's box sitting on main AHEAD of the latest tag would otherwise
# be silently rewound. rc 0 = NEW is an ancestor of OLD (backwards); rc 1 =
# it is not (the normal forward case); rc > 1 = the shallow history cannot
# answer, and the check is skipped rather than guessed at.
BACKWARDS=0
MB_ERR="$(mktemp)"
if git merge-base --is-ancestor "$NEW_SHA" "$OLD_SHA" 2>"$MB_ERR"; then
    BACKWARDS=1
fi
rm -f "$MB_ERR"

# ─── Change classification (D3) ──────────────────────────────────────
mapfile -t CHANGED_FILES < <(git diff --name-only "$OLD_SHA" "$NEW_SHA")

ACTION_IMAGES=0
ACTION_APP=0
ACTION_REVENDOR=0
CNT_APP=0; CNT_DOCKER=0; CNT_OTHER=0

for f in "${CHANGED_FILES[@]}"; do
    case "$f" in
        docker/*|docker-compose*.yml)
            CNT_DOCKER=$((CNT_DOCKER + 1))
            ACTION_IMAGES=1
            ;;
        app/composer.json|app/composer.lock)
            CNT_APP=$((CNT_APP + 1))
            ACTION_APP=1
            ACTION_REVENDOR=1
            ;;
        app/*)
            CNT_APP=$((CNT_APP + 1))
            ACTION_APP=1
            ;;
        *)
            CNT_OTHER=$((CNT_OTHER + 1))
            ;;
    esac
done

# ─── Image path derivation (D3, re-derive, do not guess) ────────────
COMPOSE_ARGS=(compose)
PULL_ARGS=(compose -f docker-compose.yml -f docker-compose.prod.yml)
BUILD_ARGS=(compose)

GPU=0
[ "$(get_env HAYSTACK_DEVICE)" = "gpu" ] && GPU=1
CUDA_TAG="$(get_env HAYSTACK_CUDA_VERSION)"
[ -n "$CUDA_TAG" ] || CUDA_TAG='cu124'
CUDA_FORCE_BUILD=0
[ "$GPU" -eq 1 ] && [ "$CUDA_TAG" != 'cu124' ] && CUDA_FORCE_BUILD=1

if [ "$GPU" -eq 1 ]; then
    PULL_ARGS=(compose -f docker-compose.yml -f docker-compose.prod-gpu.yml)
    BUILD_ARGS=(compose -f docker-compose.yml -f docker-compose.gpu.yml)
fi

# Inspect the running container, do not guess from .env alone: an install
# that fell back from pull to build (or vice versa) may disagree with what
# .env implies. No running container -> fall back to install.sh's own
# behaviour (try the pull, build if it fails).
IMAGE_PATH='unknown'
HAYSTACK_CID="$(docker "${PULL_ARGS[@]}" ps -q haystack 2>/dev/null | head -1)"
if [ -n "$HAYSTACK_CID" ]; then
    IMAGE_REF="$(docker inspect -f '{{.Config.Image}}' "$HAYSTACK_CID" 2>/dev/null || true)"
    case "$IMAGE_REF" in
        ghcr.io/*) IMAGE_PATH='pull' ;;
        *)         IMAGE_PATH='build' ;;
    esac
fi

if [ "$IMAGE_PATH" = 'build' ] || [ "$CUDA_FORCE_BUILD" -eq 1 ]; then
    COMPOSE_ARGS=("${BUILD_ARGS[@]}")
else
    COMPOSE_ARGS=("${PULL_ARGS[@]}")
fi

# ─── The -QoL* image gap (D3 / Q3 follow-on) ─────────────────────────
# A -QoL* tag publishes no images; docker-compose.prod.yml still pins the
# base release's tag in-tree. A pull-path install whose target also changed
# docker/** would otherwise pull the images it already has and report
# success while running code superseded by the release it "updated" to.
# Detected by mechanism (the pinned tag does not move), not by tag spelling,
# so it also catches a botched release or the opt-in main channel.
extract_image_tag() {  # extract_image_tag <docker-compose.prod.yml text>
    printf '%s\n' "$1" | sed -n 's/.*HDP_IMAGE_TAG:-\([^}]*\)}.*/\1/p' | head -1
}
IMAGE_TAG_OLD="$(get_env HDP_IMAGE_TAG)"
[ -n "$IMAGE_TAG_OLD" ] || IMAGE_TAG_OLD="$(extract_image_tag "$(cat docker-compose.prod.yml 2>/dev/null || true)")"
IMAGE_TAG_NEW="$(get_env HDP_IMAGE_TAG)"
[ -n "$IMAGE_TAG_NEW" ] || IMAGE_TAG_NEW="$(extract_image_tag "$(git show "$NEW_SHA:docker-compose.prod.yml" 2>/dev/null || true)")"

IMAGE_GAP=0
if [ "$IMAGE_PATH" = 'pull' ] && [ "$ACTION_IMAGES" -eq 1 ] \
    && [ -n "$IMAGE_TAG_OLD" ] && [ "$IMAGE_TAG_NEW" = "$IMAGE_TAG_OLD" ]; then
    IMAGE_GAP=1
fi

# ─── .env drift (D4) ─────────────────────────────────────────────────
NEW_ENV_EXAMPLE="$(git show "$NEW_SHA:.env.example" 2>/dev/null || true)"
declare -a ENV_NEW_DEFAULTED=()
declare -a ENV_NEW_EMPTY=()
while IFS= read -r line; do
    case "$line" in
        [A-Za-z_]*=*)
            key="${line%%=*}"
            val="${line#*=}"
            grep -q "^${key}=" "$ENV_FILE" 2>/dev/null && continue
            if [ -n "$val" ]; then
                ENV_NEW_DEFAULTED+=("$key=$val")
            else
                ENV_NEW_EMPTY+=("$key")
            fi
            ;;
    esac
done <<< "$NEW_ENV_EXAMPLE"

declare -a ENV_GONE=()
while IFS= read -r line; do
    case "$line" in
        [A-Za-z_]*=*)
            key="${line%%=*}"
            printf '%s\n' "$NEW_ENV_EXAMPLE" | grep -q "^${key}=" && continue
            ENV_GONE+=("$key")
            ;;
    esac
done < "$ENV_FILE"

# ─── Plan block (D7) — always printed, always before anything changes ─
print_plan() {
    step "Plan"
    printf '  %-16s %s\n' "release" "$TARGET_LABEL"
    printf '  %-16s %s (%s) -> %s (%s)\n' "commit" "$OLD_SHA_SHORT" "$OLD_SUBJECT" "$NEW_SHA_SHORT" "$NEW_SUBJECT"
    if [ "$DETACHED" -eq 1 ]; then
        warn "HEAD is detached — the reset will move it with nothing tracking the branch."
    else
        printf '  %-16s %s\n' "branch" "$CURRENT_BRANCH"
    fi
    printf '  %-16s app/ %d  ·  docker/ %d  ·  other %d\n' "files changed" "$CNT_APP" "$CNT_DOCKER" "$CNT_OTHER"

    printf '\n'
    info "Actions:"
    [ "$ACTION_IMAGES" -eq 1 ]   && note "  IMAGES    pull-or-build the three built services"
    [ "$ACTION_APP" -eq 1 ]      && note "  APP       restart the wiki, run docker/setup.sh (update.php)"
    [ "$ACTION_REVENDOR" -eq 1 ] && note "  REVENDOR  APP, plus a forced composer re-run"
    if [ "$ACTION_IMAGES" -eq 0 ] && [ "$ACTION_APP" -eq 0 ]; then
        note "  NONE      nothing in docker/ or app/ changed"
    fi
    if [ "$COMPOSER_LOCK_DIRTY" -eq 1 ]; then
        printf '\n'
        note "app/composer.lock is locally modified — docker/setup.sh rewrote it on"
        note "first boot (F7/F8). Safe to reset; the next composer run re-strips it."
    fi

    if [ "$BACKWARDS" -eq 1 ]; then
        printf '\n'
        warn "$TARGET_LABEL is BEHIND this checkout's current HEAD."
        note "  Resetting to it moves the tree BACKWARDS."
    fi

    if [ "$IMAGE_GAP" -eq 1 ]; then
        printf '\n'
        warn "$TAG publishes no images of its own; the published set stays at"
        note "  $IMAGE_TAG_OLD, and this release changed docker/**. Pulling would"
        note "  re-fetch the images you already have."
    fi

    if [ ${#ENV_NEW_DEFAULTED[@]} -gt 0 ] || [ ${#ENV_NEW_EMPTY[@]} -gt 0 ] || [ ${#ENV_GONE[@]} -gt 0 ]; then
        printf '\n'
        info ".env drift:"
        for kv in "${ENV_NEW_DEFAULTED[@]:-}"; do
            [ -n "$kv" ] && note "  + $kv   (new, has a default — offered below)"
        done
        for k in "${ENV_NEW_EMPTY[@]:-}"; do
            [ -n "$k" ] && note "  ? $k   (new, needs your input — run ./install.sh)"
        done
        for k in "${ENV_GONE[@]:-}"; do
            [ -n "$k" ] && note "  - $k   (in .env, no longer in .env.example — left as-is)"
        done
    fi

    printf '\n'
    info "docker ${COMPOSE_ARGS[*]} …"
}

print_plan

if [ "$CHECK_ONLY" -eq 1 ]; then
    printf '\n'
    note "--check: nothing changed but the git objects the fetch brought in."
    exec 3<&- 2>/dev/null || true
    exit 0
fi

# ─── Confirms, in the order the plan block raised them ───────────────
if [ "$IMAGE_GAP" -eq 1 ]; then
    printf '\n'
    if confirm "Build the three services from the updated source instead?" y; then
        COMPOSE_ARGS=("${BUILD_ARGS[@]}")
    else
        info "Declined. Two honest alternatives:"
        note "  wait for the next base v* release, which does publish images"
        note "  build by hand later: docker ${BUILD_ARGS[*]} build opensearch haystack chatbot-proxy"
        refuse "aborting — a docker/** change would ship unbuilt. Nothing changed."
    fi
fi

if [ "$BACKWARDS" -eq 1 ]; then
    printf '\n'
    if ! confirm "This moves the tree BACKWARDS — continue?" n; then
        printf '\n'
        ok "Nothing changed."
        exec 3<&- 2>/dev/null || true
        exit 0
    fi
fi

printf '\n'
if ! confirm "Proceed with this update?" y; then
    printf '\n'
    ok "Nothing changed."
    exec 3<&- 2>/dev/null || true
    exit 0
fi

# ─── From here on, destructive steps are allowed ─────────────────────
UPDATE_STARTED=0

print_rollback_block() {
    printf '\n'
    warn "Rollback (the old tree is at $OLD_SHA_SHORT):"
    printf '\n'
    printf '    cd %s\n' "$REPO_ROOT"
    printf '    git reset --hard %s\n' "$OLD_SHA"
    printf '    docker %s up -d --build\n' "${COMPOSE_ARGS[*]}"
    printf '    docker %s exec mediawiki bash /setup.sh\n' "${COMPOSE_ARGS[*]}"
    printf '\n'
    note "The database is NOT rolled back automatically — update.php migrates"
    note "schema forward only. See docs/dev/upgrade-runbook.md \"Rollback\"."
}

fail_rollback() {  # fail_rollback <message>
    printf '\n'
    warn "$1"
    print_rollback_block
    exec 3<&- 2>/dev/null || true
    exit 1
}

on_interrupt() {
    trap - INT TERM
    printf '\n\n'
    if [ "$UPDATE_STARTED" -eq 1 ]; then
        print_rollback_block
    else
        warn "Interrupted. Nothing was changed."
    fi
    exec 3<&- 2>/dev/null || true
    exit 130
}
trap on_interrupt INT TERM

# ─── Pre-update database dump (D6 / Q2) ──────────────────────────────
if [ "$ACTION_APP" -eq 1 ]; then
    printf '\n'
    if confirm "Take a database backup before updating?" y; then
        mkdir -p backups
        DUMP_FILE="backups/pre-update-$(date -u +%Y%m%dT%H%M%SZ).sql.gz"
        DB_ROOT_PASS="$(get_env HDP_DB_ROOT_PASSWORD)"
        info "docker ${COMPOSE_ARGS[*]} exec -T mariadb mysqldump -u root --all-databases | gzip > $DUMP_FILE"
        # -T + < /dev/null: the same pipe-safety guard as the setup.sh exec
        # below. MYSQL_PWD travels via -e into the container's environment,
        # never in argv — the same rule docker/setup.sh follows for the same
        # reason (/proc/<pid>/cmdline is world-readable).
        if docker "${COMPOSE_ARGS[@]}" exec -T -e MYSQL_PWD="$DB_ROOT_PASS" mariadb \
                mysqldump -u root --all-databases < /dev/null | gzip > "$DUMP_FILE"; then
            chmod 600 "$DUMP_FILE"
            ok "Backup written to $DUMP_FILE"
        else
            warn "Backup did not complete — see the output above."
            rm -f "$DUMP_FILE"
        fi
    else
        note "No backup taken. A code rollback after update.php runs needs one —"
        note "see docs/dev/upgrade-runbook.md \"Rollback\"."
    fi
fi

step "Applying update"

# 1. Stop the two containers that serve/write against the live schema —
#    APP only. mediawiki (FPM) is left running so nothing else flaps; it is
#    restarted at step 7 for the opcache.
if [ "$ACTION_APP" -eq 1 ]; then
    info "docker ${COMPOSE_ARGS[*]} stop mediawiki-web mediawiki-jobrunner"
    docker "${COMPOSE_ARGS[@]}" stop mediawiki-web mediawiki-jobrunner \
        || fail_rollback "could not stop mediawiki-web/mediawiki-jobrunner"
fi

# 2. The tree swap.
info "git reset --hard $NEW_SHA_SHORT"
git reset --hard "$NEW_SHA" || fail_rollback "git reset --hard failed"
UPDATE_STARTED=1
ok "Tree is now at $NEW_SHA_SHORT ($TARGET_LABEL)"

# 3. .env key append (D4) — new keys with a documented default only.
if [ ${#ENV_NEW_DEFAULTED[@]} -gt 0 ]; then
    printf '\n'
    info ".env.example gained ${#ENV_NEW_DEFAULTED[@]} key(s) with a default:"
    for kv in "${ENV_NEW_DEFAULTED[@]}"; do
        note "  $kv"
    done
    if confirm "Append them to .env?" y; then
        cp -p "$ENV_FILE" "$ENV_FILE.bak-update" && chmod 600 "$ENV_FILE.bak-update"
        for kv in "${ENV_NEW_DEFAULTED[@]}"; do
            printf '%s\n' "$kv" >> "$ENV_FILE"
        done
        ok "Appended. Previous .env saved to .env.bak-update."
    fi
fi
if [ ${#ENV_NEW_EMPTY[@]} -gt 0 ]; then
    printf '\n'
    warn "${#ENV_NEW_EMPTY[@]} new key(s) need your input (no safe default):"
    for k in "${ENV_NEW_EMPTY[@]}"; do
        note "  $k"
    done
    note "Run ./install.sh from this checkout — it pre-fills every answer from"
    note "the existing .env and only changes what you tell it to."
fi

# 4. Re-vendor (REVENDOR only), confirmed separately — several minutes, needs
#    network. Declining leaves setup.sh's cached vendor/, so its composer
#    step will be skipped — a wiki whose code and dependencies disagree.
REVENDOR_DONE=0
if [ "$ACTION_REVENDOR" -eq 1 ]; then
    printf '\n'
    warn "app/composer.lock changed — this is the re-vendor case."
    if confirm "Remove app/vendor so setup.sh reinstalls it now?" y; then
        rm -rf app/vendor
        REVENDOR_DONE=1
    else
        warn "Declined. The wiki's code and its dependencies now disagree until"
        note "  app/vendor is removed by hand and setup.sh is re-run."
    fi
fi

# 5. Pull-or-build (IMAGES only). COMPOSE_ARGS was already fixed to the build
#    path above if the -QoL gap fired; otherwise probe the pull the way
#    install.sh does — the probe is also the work.
if [ "$ACTION_IMAGES" -eq 1 ]; then
    printf '\n'
    if [ "$IMAGE_GAP" -eq 1 ] || [ "$CUDA_FORCE_BUILD" -eq 1 ] || [ "$IMAGE_PATH" = 'build' ]; then
        info "docker ${BUILD_ARGS[*]} build opensearch haystack chatbot-proxy"
        COMPOSE_ARGS=("${BUILD_ARGS[@]}")
        docker "${COMPOSE_ARGS[@]}" build opensearch haystack chatbot-proxy \
            || fail_rollback "image build failed"
    else
        info "docker ${PULL_ARGS[*]} pull"
        if docker "${PULL_ARGS[@]}" pull; then
            COMPOSE_ARGS=("${PULL_ARGS[@]}")
        else
            warn "pull failed — building from source instead."
            COMPOSE_ARGS=("${BUILD_ARGS[@]}")
            docker "${COMPOSE_ARGS[@]}" build opensearch haystack chatbot-proxy \
                || fail_rollback "image build failed"
        fi
    fi
fi

# 6. Reconcile — starts whatever step 1 stopped, picks up any new/rebuilt
#    images.
printf '\n'
info "docker ${COMPOSE_ARGS[*]} up -d"
docker "${COMPOSE_ARGS[@]}" up -d || fail_rollback "docker compose up failed"

# 7. Restart mediawiki for the opcache (APP only — F3).
if [ "$ACTION_APP" -eq 1 ]; then
    info "docker ${COMPOSE_ARGS[*]} restart mediawiki"
    docker "${COMPOSE_ARGS[@]}" restart mediawiki \
        || fail_rollback "could not restart mediawiki"
fi

# ─── Health gate (D6) ────────────────────────────────────────────────
compose_state() {
    local cid
    cid="$(docker "${COMPOSE_ARGS[@]}" ps -q "$1" 2>/dev/null | head -1)"
    [ -n "$cid" ] || { printf 'gone'; return 0; }
    docker inspect \
        -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' \
        "$cid" 2>/dev/null || printf 'gone'
}

wait_ready() {  # wait_ready <label> <timeout-s> <service>...
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

printf '\n'
wait_ready "database and search  " 300 mariadb opensearch \
    || fail_rollback "mariadb/opensearch never became healthy"
wait_ready "wiki container      " 180 mediawiki mediawiki-web \
    || fail_rollback "mediawiki/mediawiki-web never became healthy"

# 9. setup.sh — APP only. Same idiom as install.sh's guarded exec, so the
#    D5 pipe-safety sweep's anchor is the identical string.
if [ "$ACTION_APP" -eq 1 ]; then
    printf '\n'
    step "First-boot setup (re-run)"
    info "docker ${COMPOSE_ARGS[*]} exec -T mediawiki bash /setup.sh"
    printf '\n'
    docker "${COMPOSE_ARGS[@]}" exec -T mediawiki bash /setup.sh < /dev/null \
        || fail_rollback "docker/setup.sh exited non-zero"
fi

# 10. Final health pass + HTTP probe.
printf '\n'
ALL_SERVICES=(mariadb opensearch mediawiki mediawiki-web mediawiki-jobrunner haystack chatbot-proxy)
wait_ready "all services         " 60 "${ALL_SERVICES[@]}" \
    || fail_rollback "not every service reported healthy after the update"

MW_PORT="$(get_env MW_DOCKER_PORT)"
[ -n "$MW_PORT" ] || MW_PORT=8080
WIKI_URL="http://localhost:$MW_PORT/w/"
if ! curl -sf -o /dev/null "$WIKI_URL"; then
    fail_rollback "the wiki did not answer at $WIKI_URL"
fi

trap - INT TERM
exec 3<&- 2>/dev/null || true

# ─── Summary (D7) ────────────────────────────────────────────────────
step "Updated"

printf '  %-16s %s -> %s\n' "release" "$OLD_SHA_SHORT -> $TARGET_LABEL" "$NEW_SHA_SHORT"
printf '\n'
info "Actions taken:"
[ "$ACTION_APP" -eq 1 ]                       && ok "  APP — setup.sh / update.php ran"
[ "$ACTION_REVENDOR" -eq 1 ] && [ "$REVENDOR_DONE" -eq 1 ] && ok "  REVENDOR — app/vendor reinstalled"
[ "$ACTION_IMAGES" -eq 1 ]                    && ok "  IMAGES — ${COMPOSE_ARGS[*]}"
printf '\n'
info "Services:"
for svc in "${ALL_SERVICES[@]}"; do
    printf '    %-20s %s\n' "$svc" "$(compose_state "$svc")"
done
printf '\n'
printf '  %sWiki:%s %s\n' "$C_BLD" "$C_OFF" "$WIKI_URL"

for f in "${CHANGED_FILES[@]}"; do
    case "$f" in
        docker/haystack/*)
            printf '\n'
            note "docker/haystack/** changed — re-ingestion may be worth running:"
            note "  docker ${COMPOSE_ARGS[*]} exec haystack python3 ingest_hdp_wiki.py --missing-only"
            break
            ;;
    esac
done
printf '\n'
exit 0
