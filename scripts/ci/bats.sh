#!/usr/bin/env bash
# ============================================================
# Wave 2 — the bats runner.
#
# Two suites:
#   infisical_loader.bats  docker/infisical-loader.sh — the Wave 0 security fixes
#   upgrade_report.bats    verify-patches.sh --upgrade-report — every state
#
# docker/setup.sh is the other obvious candidate and is not testable at this
# level: 557 lines, `set -euo pipefail`, `cd "$MW"` on line 18, and a
# top-to-bottom installer body. Sourcing it in bats runs the installer, so
# "assert setup.sh exits non-zero on a simulated patch failure" means standing
# up MariaDB and composer first. That is an integration test and it belongs in
# Wave 3, not here.
#
# infisical-loader.sh is the opposite: it is designed to be sourced, it has a
# clean early-return path, and the two Wave 0 fixes it carries both regress
# silently.
#
#   a3f505d98  the `:-` defaults that stop `set -u` aborting setup.sh
#   61a1406b3  the client secret on stdin rather than argv
#   2ce40551f  the bearer token on stdin rather than argv
#
# Nothing about a regression in any of those produces an error message. The
# first one produces a wiki that never installs; the other two produce a
# container whose secrets are readable via /proc/<pid>/cmdline by anything else
# running in it.
#
# Runs bats from a digest-pinned image. The tag is pinned by digest rather than
# by `:latest` for the reason ruff.toml records at length: a moving tag means
# CI's verdict can change with no commit to blame, and this repo has already
# been bitten by exactly that.
#
# curl is a test double (tests/bats/helpers/bin/curl). jq, bash and the loader
# itself are real — the image ships bash and ps, and jq/curl are added at
# start, which takes about two seconds.
#
# Exit: 0 passed · 1 failed · 77 could not run (check.sh renders this SKIP,
#       which per that script's contract is explicitly not a pass)
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT" || exit 1

# Pinned by digest. Kept in step with .gitlab-ci.yml and scripts/check.sh.
IMG_BATS="bats/bats@sha256:5322b877351fda0cc435de8c6116de7d0a2ec79d7c680132a0ef329a633bc66f"
BATS_PINNED_VERSION="1.14.0"

TEST_DIR="tests/bats"

have()        { command -v "$1" >/dev/null 2>&1; }
have_docker() { have docker && docker info >/dev/null 2>&1; }

[ -d "$TEST_DIR" ] || { echo "  $TEST_DIR does not exist"; exit 77; }

# A host bats is a convenience, not the source of truth — same posture
# check.sh takes for ruff. It also needs jq, which the image installs itself.
if have bats && have jq; then
    v="$(bats --version 2>/dev/null | awk '{print $2}')"
    if [ -n "$v" ] && [ "$v" != "$BATS_PINNED_VERSION" ]; then
        echo "note: host bats $v differs from the pinned $BATS_PINNED_VERSION"
    fi
    exec bats "$TEST_DIR"
fi

if ! have_docker; then
    echo "  no bats+jq on PATH and no usable docker"
    exit 77
fi

# --entrypoint sh because the image's entrypoint is bats itself, and the
# dependencies have to be installed before the tests run:
#   jq, curl   the infisical-loader suite (curl is shadowed by a test double)
#   python3    read-manifest.py, which verify-patches.sh shells out to
#   patch      the diff-mode probe in the upgrade report
# The last two are why upgrade_report.bats exists at all: the image ships
# neither, and without them every one of its tests fails on the tool rather
# than on the behaviour.
docker run --rm --entrypoint sh \
    -v "$REPO_ROOT":/w -w /w "$IMG_BATS" \
    -c "apk add --no-cache jq curl python3 patch >/dev/null 2>&1 && bats $TEST_DIR"
