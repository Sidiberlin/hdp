#!/usr/bin/env bash
# ============================================================
# T0 — .env.example completeness.
#
# Every new user starts at `cp .env.example .env`. If compose interpolates a
# variable the template never mentions, that user gets a stack that comes up
# and misbehaves rather than one that refuses to start: Compose substitutes an
# unset variable with the empty string and exits 0. `docker compose config`
# therefore does NOT catch this, which is why it needs its own check.
#
# Two directions, both of which matter:
#   missing — used with no default, absent from the template  -> hard failure
#   unused  — declared in the template, referenced nowhere    -> reported, not fatal
#
# Variables written as ${VAR:-default} are excluded from the first check:
# Compose supplies the fallback itself, and the template deliberately leaves
# the memory limits and HDP_BIND_ADDR commented out.
#
# Exit: 0 complete · 1 a no-default variable is undeclared
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT" || exit 1

[ -f .env.example ]      || { echo "  .env.example is missing"; exit 1; }
[ -f docker-compose.yml ] || { echo "  docker-compose.yml is missing"; exit 1; }

# Declared in the template (ignoring comments), e.g. "# HDP_X=1" does not count.
declared="$(grep -oE '^[A-Za-z_][A-Za-z0-9_]*=' .env.example | tr -d '=' | sort -u)"

# Referenced by compose with no default: ${VAR}, not ${VAR:-x} or ${VAR:?x}.
required="$(grep -oE '\$\{[A-Za-z_][A-Za-z0-9_]*\}' docker-compose.yml \
            | sed -E 's/^\$\{(.*)\}$/\1/' | sort -u)"

# Referenced at all, with or without a default — used for the unused report.
referenced="$(grep -oE '\$\{[A-Za-z_][A-Za-z0-9_]*' docker-compose.yml \
              | sed 's/^\${//' | sort -u)"

missing="$(comm -23 <(printf '%s\n' "$required") <(printf '%s\n' "$declared"))"
unused="$(comm -23 <(printf '%s\n' "$declared") <(printf '%s\n' "$referenced"))"

rc=0
if [ -n "$missing" ]; then
    echo "  MISSING from .env.example (compose uses these with no default, so"
    echo "  they silently become the empty string):"
    printf '%s\n' "$missing" | sed 's/^/    /'
    rc=1
fi

if [ -n "$unused" ]; then
    # Not a failure. Plenty of these are read by the containers rather than by
    # compose interpolation — HDP_LLM_MODEL and the embedding settings reach
    # the entrypoints through env_file, and infisical-loader.sh reads its own.
    # Reported so a genuinely dead variable is visible, not to gate on it.
    echo "  note: declared in .env.example but not interpolated by compose"
    echo "        (may still be consumed via env_file — informational only):"
    printf '%s\n' "$unused" | sed 's/^/    /'
fi

[ "$rc" -eq 0 ] && echo "  .env.example declares every no-default \${VAR} compose uses"
exit "$rc"
