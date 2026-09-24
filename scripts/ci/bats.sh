#!/usr/bin/env bash
# ============================================================
# Wave 2 — the bats runner.
#
# Five suites:
#   infisical_loader.bats  docker/infisical-loader.sh — the Wave 0 security fixes
#   install_pipe.bats      install.sh — the pipe-fed (-T + </dev/null) guards
#   update_pipe.bats       update.sh — the same guards, its own count pin
#   upgrade_report.bats    verify-patches.sh --upgrade-report — every state
#   t4_disk_guard.bats     scripts/ci/lib/stack.sh — the T4 free-space maths,
#                          pinned to the two runs that produced the numbers
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
#   41bc523f5  the `:-` defaults that stop `set -u` aborting setup.sh
#   f8bee773b  the client secret on stdin rather than argv
#   89428d26f  the bearer token on stdin rather than argv
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
# check.sh takes for ruff.
#
# The tool list is the same one the docker branch installs below, and it has to
# be: this path checked only for bats and jq, so a host with those two and no
# `patch` ran upgrade_report.bats against a missing tool and failed "on the tool
# rather than on the behaviour" — which is the exact thing the docker branch's
# own comment says it installs them to prevent. A host missing one of them now
# falls through to docker instead of failing. `git` joined this list (and the
# docker branch's apk line) the same way `patch` did: update_pipe.bats grew a
# fixture that shells out to it, and a bats image with none of these preinstalled
# failed the new tests on "command not found" (127) rather than on the
# behaviour under test.
HOST_TOOLS="bats jq python3 patch git"
host_ready() {
    local t
    for t in $HOST_TOOLS; do have "$t" || return 1; done
    return 0
}

if host_ready; then
    v="$(bats --version 2>/dev/null | awk '{print $2}')"
    if [ -n "$v" ] && [ "$v" != "$BATS_PINNED_VERSION" ]; then
        echo "note: host bats $v differs from the pinned $BATS_PINNED_VERSION"
    fi
    exec bats "$TEST_DIR"
fi

if ! have_docker; then
    missing=""
    for t in $HOST_TOOLS; do have "$t" || missing="$missing $t"; done
    # Name what is missing. "no bats+jq" sent people to install bats when the
    # gap was `patch`.
    echo "  no usable docker, and the host is missing:$missing"
    echo "  (the suite needs$(printf ' %s' $HOST_TOOLS) — python3 for read-manifest.py,"
    echo "  patch for the diff-mode probe in the upgrade report, and git for"
    echo "  update_pipe.bats's port-migration fixture)"
    exit 77
fi

# --entrypoint sh because the image's entrypoint is bats itself, and the
# dependencies have to be installed before the tests run:
#   jq, curl   the infisical-loader suite (curl is shadowed by a test double)
#   python3    read-manifest.py, which verify-patches.sh shells out to
#   patch      the diff-mode probe in the upgrade report
#   git        update_pipe.bats's port-migration fixture builds a real bare
#              remote + checkout (init/commit/tag/clone/push) to drive
#              update.sh through a pipe-fed --check the same way the
#              documented `curl | bash` invocation runs it
# The image ships none of these — bare bash and ps only — so without them
# the affected suite's tests fail on the missing tool (bats' own `command
# not found` → 127) rather than on the behaviour under test, which is
# exactly what host_ready()'s HOST_TOOLS list above exists to prevent on the
# host path. This apk line is the docker path's answer to the same gap: it
# would silently break the exact same way with an unmatched host.
docker run --rm --entrypoint sh \
    -v "$REPO_ROOT":/w -w /w "$IMG_BATS" \
    -c "apk add --no-cache jq curl python3 patch git >/dev/null 2>&1 && bats $TEST_DIR"
