#!/usr/bin/env bash
# ============================================================
# Track B — poll the upstream release feeds (§10.3).
#
# Not a gate. It runs on a schedule, not on a push, because the answer changes
# when upstream ships and not when we commit — and because a job that fails on
# somebody else's release would block every merge for a reason no PR can fix.
#
#   scripts/ci/release-watch.sh          human-readable
#   scripts/ci/release-watch.sh --json   the report the workflow files as an issue
#
# What it compares:
#   VERSIONS.yml mw_core   vs  releases.wikimedia.org (our branch, and newer branches)
#   VERSIONS.yml bluespice vs  packages.bluespice.com (bluespice/foundation)
#
# Exit: 0 up to date · 1 upstream has moved · 77 a feed did not answer
#
# The human backstop is the mediawiki-announce mailing list — MediaWiki
# security releases are announced there first, and this job at best notices
# them a week later. See SECURITY.md.
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT" || exit 2

command -v python3 >/dev/null 2>&1 || { echo "  python3 is required" >&2; exit 2; }

exec python3 scripts/lib/release_watch.py "$@"
