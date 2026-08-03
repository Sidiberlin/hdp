#!/usr/bin/env bash
# ============================================================
# T0 — known CVEs in app/composer.lock (§10.3 Track A).
#
# `composer audit --locked` reads the lockfile and the packagist advisory
# database. It is the only automated CVE signal available for the 148
# composer-visible packages, and it is blind to everything else in this repo:
# MediaWiki core is vendored source rather than a composer dependency (that is
# the release-watch job, Track B), and the two frozen packages are stripped
# from the lockfile entirely (Track C — see VERSIONS.yml and SECURITY.md).
#
# The result is compared against docker/ci/composer-audit-baseline.json rather
# than used directly, because the tree carries 34 known advisories inherited
# from upstream's dependency choices and a gate that is red on every push is a
# gate people stop reading. The comparison fails on anything NEW, which is the
# question worth asking on a push. See scripts/lib/audit_baseline.py.
#
#   scripts/ci/composer-audit.sh                   the gate
#   scripts/ci/composer-audit.sh --report          print the full audit table
#   scripts/ci/composer-audit.sh --update-baseline re-record what is accepted
#
# Exit: 0 nothing new · 1 a new advisory · 2 cannot run · 77 no advisory DB
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT" || exit 2

# Kept in step with scripts/check.sh's IMG_COMPOSER and the .gitlab-ci.yml job.
IMG_COMPOSER="composer:2.8"
BASELINE="docker/ci/composer-audit-baseline.json"

MODE=gate
case "${1:-}" in
    --report)          MODE=report ;;
    --update-baseline) MODE=update ;;
    "")                ;;
    *) echo "composer-audit.sh: unknown option '$1'" >&2; exit 2 ;;
esac

command -v python3 >/dev/null 2>&1 || { echo "  python3 is required" >&2; exit 2; }

# `--locked` audits the lockfile without installing anything, so this needs no
# vendor/ and no PHP extensions — just composer and the network.
run_composer() {
    if command -v composer >/dev/null 2>&1; then
        (cd app && composer "$@" 2>/dev/null)
    elif docker info >/dev/null 2>&1; then
        docker run --rm -v "$REPO_ROOT/app":/app -w /app "$IMG_COMPOSER" composer "$@" 2>/dev/null
    else
        return 127
    fi
}

if [ "$MODE" = report ]; then
    run_composer audit --locked --no-interaction --abandoned=report
    exit $?
fi

REPORT="$(mktemp)"
trap 'rm -f "$REPORT"' EXIT

# --abandoned=ignore: an abandoned package is a maintenance signal, not a
# vulnerability, and composer exits non-zero for it. doctrine/cache is
# abandoned today and there is nothing to fix.
run_composer audit --locked --no-interaction --abandoned=ignore --format=json >"$REPORT"
rc=$?

if [ "$rc" -eq 127 ]; then
    echo "  no composer on PATH and no docker — cannot reach the advisory database"
    exit 77
fi

# composer exits 1 when it finds advisories and 0 when it does not; either way
# it writes the JSON. An empty or unparseable file means it never got to the
# advisory database at all — offline, DNS, a proxy — and that is a skip, not a
# pass. Reporting "no new advisories" because nothing could be fetched is the
# one failure this gate must not have.
if [ ! -s "$REPORT" ] || ! python3 -c 'import json,sys; json.load(open(sys.argv[1]))' "$REPORT" 2>/dev/null; then
    echo "  composer audit produced no usable output (exit $rc)."
    echo "  This gate needs the packagist advisory database; it did not answer."
    echo "  Re-run with --report to see composer's own output."
    exit 77
fi

if [ "$MODE" = update ]; then
    python3 scripts/lib/audit_baseline.py "$REPORT" "$BASELINE" app/composer.lock --update
    exit $?
fi

python3 scripts/lib/audit_baseline.py "$REPORT" "$BASELINE" app/composer.lock
