#!/usr/bin/env bash
# ============================================================
# Wave 2 — the pytest runner.
#
# The suite is split into two tiers, and the split is the point:
#
#   unit      tests/unit/     — standard library only. render_pipeline.render,
#                               the wikitext pure functions and
#                               build_result_from_haystack import nothing
#                               outside stdlib, so this tier runs on a bare
#                               python:3.11 in about two seconds. This is the
#                               tier a contributor runs on every save.
#
#   haystack  tests/haystack/ — needs real haystack-ai and envsubst on PATH.
#                               to_native() branches on Document, Answer,
#                               GeneratedAnswer and ExtractedAnswer, and
#                               load_pipeline() shells out to envsubst before
#                               calling Pipeline.loads.
#
# Nothing here is mocked. The reason the second tier is affordable is a
# measurement, not an assumption:
#
#     docker run --rm python:3.11-slim  pip install haystack-ai==2.15.0
#     -> 24s, 172MB site-packages, no torch, no transformers
#
# torch and transformers arrive via sentence-transformers, a *runtime*
# dependency of the embedder that nothing under test touches. The plan had
# assumed real Haystack types meant dragging in the whole 2GB runtime image and
# that mocks might be necessary; they are not.
#
# Each tier prefers whatever already satisfies it and falls back to a pinned
# image, the same shape scripts/check.sh uses for shellcheck and ruff:
#
#   1. this host, if it already has the tier's dependencies    (0s setup)
#   2. the project's own haystack image, if it has been built  (0s setup —
#      it ships gettext-base and, since Wave 2, pytest)
#   3. python:3.11-slim + apt-get gettext-base + pip install   (~47s)
#
# Usage:
#   scripts/ci/pytest.sh                  both tiers
#   scripts/ci/pytest.sh --tier unit      stdlib tier only
#   scripts/ci/pytest.sh --tier haystack  haystack tier only
#   scripts/ci/pytest.sh -- -k to_native  args after -- go to pytest
#   scripts/ci/pytest.sh --regen-golden   rewrite the golden JSON fixtures from
#                                         the current implementation, then show
#                                         the diff. Never runs in CI: a golden
#                                         file that updates itself records
#                                         whatever the code does today, which is
#                                         the opposite of its purpose.
#
# Exit: 0 all selected tiers passed · 1 a tier failed · 2 bad usage
#       77 a tier could not be run at all (check.sh renders this as SKIP,
#          which per that script's contract is explicitly not a pass)
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT" || exit 1

# Kept in step with tests/requirements.txt and docker/haystack/Dockerfile.
IMG_PYTHON="python:3.11-slim"
HAYSTACK_IMAGE_CANDIDATES=(hdp-haystack haystack)

TIER="all"
REGEN=0
PYTEST_ARGS=()

usage() {
    sed -n '2,45p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
}

while [ $# -gt 0 ]; do
    case "$1" in
        --tier)
            shift
            [ $# -gt 0 ] || { echo "--tier needs a value (unit|haystack|all)" >&2; exit 2; }
            TIER="$1"
            ;;
        --regen-golden) REGEN=1 ;;
        -h|--help) usage; exit 0 ;;
        --) shift; PYTEST_ARGS+=("$@"); break ;;
        *) echo "unknown argument: $1" >&2; exit 2 ;;
    esac
    shift
done

case "$TIER" in
    unit|haystack|all) ;;
    *) echo "--tier must be one of: unit, haystack, all (got '$TIER')" >&2; exit 2 ;;
esac

if [ "$REGEN" -eq 1 ]; then
    # The fixtures live under tests/unit/, so regeneration is a unit-tier
    # operation regardless of what --tier said.
    TIER="unit"
    export HDP_REGEN_GOLDEN=1
    echo "regenerating golden fixtures — review the diff before committing"
fi

have()        { command -v "$1" >/dev/null 2>&1; }
have_docker() { have docker && docker info >/dev/null 2>&1; }

# Shell-quoted extra pytest args, or nothing at all.
#
# `printf '%q ' "${ARR[@]+...}"` on an EMPTY array still runs the format once
# and emits a single '' — a stray empty argument, which pytest reads as a path
# and answers by collecting from rootdir instead of the directory asked for.
# That silently ran the whole suite in place of the requested tier. Guard on
# the array length instead.
pytest_extra_args() {
    [ "${#PYTEST_ARGS[@]}" -eq 0 ] && return 0
    printf '%q ' "${PYTEST_ARGS[@]}"
}

# The one image name that exists locally, if any. `docker compose build
# haystack` names it after the project directory, so the name is not fixed;
# probe the candidates rather than hardcoding one and silently never matching.
find_haystack_image() {
    local candidate
    for candidate in "${HAYSTACK_IMAGE_CANDIDATES[@]}"; do
        if docker image inspect "$candidate" >/dev/null 2>&1; then
            echo "$candidate"
            return 0
        fi
    done
    # compose names images <project>-<service>; the project defaults to the
    # directory name, which differs between a dev clone and the box.
    docker images --format '{{.Repository}}' 2>/dev/null \
        | grep -E '(^|[-_])haystack$' | head -1
}

# ─── unit tier ──────────────────────────────────────────────────────
run_unit() {
    echo "── tier: unit (stdlib only) ──"
    if have python3 && python3 -c "import pytest" >/dev/null 2>&1; then
        python3 -m pytest tests/unit "${PYTEST_ARGS[@]+"${PYTEST_ARGS[@]}"}"
        return $?
    fi
    if have_docker; then
        docker run --rm -e HDP_REGEN_GOLDEN="${HDP_REGEN_GOLDEN:-}" \
            -v "$REPO_ROOT":/w -w /w "$IMG_PYTHON" \
            sh -c "pip install --quiet --no-cache-dir --root-user-action=ignore pytest==9.0.2 \
                   && python -m pytest tests/unit $(pytest_extra_args)"
        return $?
    fi
    echo "  no python3-with-pytest on PATH and no usable docker"
    return 77
}

# ─── haystack tier ──────────────────────────────────────────────────
run_haystack() {
    echo "── tier: haystack (real haystack-ai, no mocks) ──"

    # 1. this host already has everything
    if have python3 \
       && python3 -c "import pytest, haystack" >/dev/null 2>&1 \
       && have envsubst; then
        python3 -m pytest tests/haystack "${PYTEST_ARGS[@]+"${PYTEST_ARGS[@]}"}"
        return $?
    fi

    if ! have_docker; then
        echo "  host lacks haystack-ai/pytest/envsubst and there is no usable docker"
        return 77
    fi

    # 2. the project's own image, if it has been built. It ships gettext-base
    #    and haystack-ai already, and Wave 2 adds pytest, so setup is free.
    local image
    image="$(find_haystack_image)"
    if [ -n "$image" ] \
       && docker run --rm --entrypoint sh "$image" -c \
            'command -v envsubst >/dev/null && python -c "import pytest, haystack"' \
            >/dev/null 2>&1; then
        echo "  using the built haystack image: $image"
        docker run --rm --entrypoint sh \
            -v "$REPO_ROOT":/w -w /w "$image" \
            -c "python -m pytest tests/haystack $(pytest_extra_args)"
        return $?
    fi

    # 3. a clean slim image. ~47s: 23s apt for envsubst, 24s pip for haystack-ai.
    echo "  building the tier from $IMG_PYTHON (about 47s: apt for envsubst, pip for haystack-ai)"
    docker run --rm -e DEBIAN_FRONTEND=noninteractive \
        -v "$REPO_ROOT":/w -w /w "$IMG_PYTHON" \
        sh -c "set -e
               apt-get update -qq >/dev/null 2>&1
               apt-get install -y -qq --no-install-recommends gettext-base >/dev/null 2>&1
               pip install --quiet --no-cache-dir --root-user-action=ignore -r tests/requirements.txt
               python -m pytest tests/haystack $(pytest_extra_args)"
    return $?
}

# ─── drive ──────────────────────────────────────────────────────────
overall=0
skipped=0
ran=0

for tier in unit haystack; do
    [ "$TIER" = "all" ] || [ "$TIER" = "$tier" ] || continue
    "run_$tier"
    rc=$?
    case $rc in
        0)  ran=$(( ran + 1 )) ;;
        77) skipped=$(( skipped + 1 )) ;;
        *)  overall=1; ran=$(( ran + 1 )) ;;
    esac
done

# A tier that could not run is not a tier that passed. If nothing ran at all,
# say so with 77 rather than reporting success against zero tests.
if [ "$ran" -eq 0 ] && [ "$skipped" -gt 0 ]; then
    echo "  no tier could be run"
    exit 77
fi
if [ "$skipped" -gt 0 ] && [ "$overall" -eq 0 ] && [ "$REGEN" -eq 0 ]; then
    echo "  note: $skipped tier(s) could not run — this is not a full pass"
fi

if [ "$REGEN" -eq 1 ]; then
    echo ""
    echo "── golden fixture changes ──"
    # --porcelain covers files git does not track yet; plain `git diff` shows
    # nothing for a brand-new fixture, which would read as "no changes".
    if git rev-parse --git-dir >/dev/null 2>&1; then
        git status --porcelain -- tests/unit/fixtures/ || true
        git diff -- tests/unit/fixtures/ || true
    fi
    echo "Review the above, then commit. Nothing was verified by this run."
fi

exit "$overall"
