#!/usr/bin/env bash
# ============================================================
# The CI mirror for the three wikimedia dev images.
#
# On 2026-09-23 docker-registry.wikimedia.org stopped answering anonymous
# pulls from GitHub Actions runner IPs — an HTML anti-abuse page served as a
# `denied`, with the same tags still pullable from anywhere else. T3, T5 and
# T4 boot compose on those runners, so all three went red at once and no
# amount of retrying changes the answer.
#
# The fix is a copy of the exact tags into GHCR, which the runners can reach,
# plus three overridable image refs in docker-compose.yml. The upstream ref
# stays the default: installs, the QA box, GitLab and developers pull from
# wikimedia exactly as before, and only a job that runs --github-env is
# pointed at the mirror.
#
# The mapping lives HERE and nowhere else. Both refs are derived from
# docker-compose.yml, so a tag bump cannot leave a workflow pointing at a
# stale copy — it makes the job fail with the command that fixes it.
#
#   scripts/ci/mirror-images.sh --print        show the mapping, touch nothing
#   scripts/ci/mirror-images.sh --push         copy upstream -> GHCR
#   scripts/ci/mirror-images.sh --github-env   export the overrides into a job
#
# --push must run from a host the wikimedia registry still answers (a laptop,
# the QA box, the orchestrator) and logged in to the mirror:
#
#   docker login ghcr.io -u <github-user>   # PAT with write:packages
#   scripts/ci/mirror-images.sh --push
#
# It CANNOT run in GitHub Actions: the runner is the blocked IP, which is the
# whole reason this script exists.
#
# After the first push each package is private. Set the three to public in the
# GHCR package settings — then every job, including a pull request from a
# fork, pulls them with no credentials and no login step.
#
# Exit: 0 ok · 1 a copy or a check failed · 2 bad usage / nothing to mirror
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT" || exit 2

# Overridable so the same script can seed a GitLab registry or a private
# mirror without editing it — see the plan's remedy G.
REGISTRY="${HDP_MIRROR_REGISTRY:-ghcr.io}"
OWNER="${HDP_MIRROR_OWNER:-sidiberlin}"     # lowercase: GHCR rejects an uppercase path
PREFIX="${HDP_MIRROR_PREFIX:-hdp-mirror-}"  # never confusable with a released hdp-* image

MODE=print
while [ $# -gt 0 ]; do
    case "$1" in
        --print)      MODE=print ;;
        --push)       MODE=push ;;
        --github-env) MODE=github-env ;;
        -h|--help)    sed -n '2,40p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "mirror-images.sh: unknown option '$1' (try --help)" >&2; exit 2 ;;
    esac
    shift
done

mirror_for() { printf '%s/%s/%s%s' "$REGISTRY" "$OWNER" "$PREFIX" "${1##*/}"; }

# "VAR upstream-ref" for every overridable wikimedia image in the compose file.
overridable() {
    sed -n 's|^[[:space:]]*image:[[:space:]]*\${\([A-Za-z0-9_]*\):-\(docker-registry\.wikimedia\.org/dev/[^}]*\)}[[:space:]]*$|\1 \2|p' docker-compose.yml
}

# The same images before they were made overridable, so --push also works on a
# tree where the compose edit has not been made yet (the bootstrap case).
bare() {
    sed -n 's|^[[:space:]]*image:[[:space:]]*\(docker-registry\.wikimedia\.org/dev/[^[:space:]]*\)[[:space:]]*$|- \1|p' docker-compose.yml
}

verify() {
    docker buildx imagetools inspect "$1" >/dev/null 2>&1 && return 0
    {
        echo "  $1 is not pullable."
        echo "  Either the mirror has not got this tag yet, or its GHCR package is"
        echo "  still private. From a host the wikimedia registry still answers:"
        echo "      docker login ghcr.io -u <github-user>   # PAT with write:packages"
        echo "      scripts/ci/mirror-images.sh --push"
        echo "  then set the package to public in its GHCR package settings."
    } >&2
    return 1
}

copy() {
    # skopeo copies manifest-to-manifest without a daemon or a local pull;
    # docker is the fallback, and is lossless here because all three images
    # are single-architecture (amd64) v2 manifests, not manifest lists.
    if command -v skopeo >/dev/null 2>&1; then
        skopeo copy --all "docker://$1" "docker://$2"
    else
        docker pull "$1" && docker tag "$1" "$2" && docker push "$2"
    fi
}

if [ "$MODE" = github-env ] && [ -z "${GITHUB_ENV:-}" ]; then
    echo "mirror-images.sh: --github-env only means anything inside a GitHub Actions job" >&2
    exit 2
fi

PAIRS="$(overridable)"
[ "$MODE" = push ] && [ -z "$PAIRS" ] && PAIRS="$(bare)"
if [ -z "$PAIRS" ]; then
    echo "mirror-images.sh: docker-compose.yml names no wikimedia dev image" >&2
    exit 2
fi

rc=0
while read -r var up; do
    [ -n "$up" ] || continue
    mir="$(mirror_for "$up")"
    case "$MODE" in
        print)
            printf '%-24s %s\n                         -> %s\n' "$var" "$up" "$mir"
            ;;
        github-env)
            verify "$mir" || { rc=1; continue; }
            printf '%s=%s\n' "$var" "$mir" >> "$GITHUB_ENV"
            echo "  $var -> $mir"
            ;;
        push)
            echo "  $up -> $mir"
            copy "$up" "$mir" || rc=1
            ;;
    esac
done <<< "$PAIRS"

exit "$rc"
