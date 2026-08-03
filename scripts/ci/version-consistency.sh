#!/usr/bin/env bash
# ============================================================
# T0 — VERSIONS.yml must agree with the tree.
#
# The cheapest gate in the repository (~1s, no containers, no network) and the
# one that makes every other part of the upstream-security process meaningful:
# monitoring for "has upstream moved past us" is not a question you can ask
# until "where are we" has exactly one answer.
#
# It compares VERSIONS.yml against, in order: MW_VERSION in
# app/includes/Defines.php, every bluespice/* version in app/composer.lock,
# every app/extensions/*/extension.json, publiccode.yml's softwareVersion, the
# image tags in docker-compose.yml, docker/opensearch/Dockerfile,
# docker/haystack/Dockerfile, and the frozen-package strip list in
# docker/setup.sh.
#
# All the logic lives in scripts/lib/versions.py so that this script, the
# GitLab job, the GitHub matrix entry and scripts/check.sh are four callers of
# one implementation rather than four opinions.
#
# Exit: 0 consistent · 1 drift · 2 cannot run
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT" || exit 2

command -v python3 >/dev/null 2>&1 || {
    echo "  python3 is required to read VERSIONS.yml" >&2
    exit 2
}

[ -f VERSIONS.yml ] || {
    echo "  VERSIONS.yml is missing. It is this fork's declared version and the" >&2
    echo "  input to the release-watch job; recreate it rather than deleting the gate." >&2
    exit 2
}

exec python3 scripts/lib/versions.py check
