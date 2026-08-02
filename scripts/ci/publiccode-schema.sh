#!/usr/bin/env bash
# ============================================================
# T0 — publiccode.yml schema validation.
#
# openCode validates this file, and it is the repository's entry in a
# public-sector software catalogue. A schema error there is a broken listing
# for a BMBF-funded deliverable, which is a worse failure than a lint warning.
#
# Uses the official parser from the publiccode.yml maintainers rather than a
# hand-rolled schema, so it stays correct as the spec moves.
#
# --no-network is deliberate: the parser otherwise dereferences every URL in
# the file, which makes the job slow, flaky, and dependent on external hosts
# being up. Structure is what CI can meaningfully assert.
#
# Warnings are printed but do not fail. Today they are: the file declares
# publiccodeYmlVersion 0.2 while the parser wants '0', and legal.repoOwner and
# description.*.genericName are deprecated. All three are content decisions
# with catalogue-visible consequences, not schema errors, and belong with the
# version-consistency work rather than in a lint gate.
#
# Exit: 0 valid · 1 schema error
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT" || exit 1

IMG_PUBLICCODE="${IMG_PUBLICCODE:-italia/publiccode-parser-go:latest}"

[ -f publiccode.yml ] || { echo "  publiccode.yml is missing"; exit 1; }

if command -v publiccode-parser >/dev/null 2>&1; then
    out="$(publiccode-parser -no-network publiccode.yml 2>&1)"; rc=$?
elif docker info >/dev/null 2>&1; then
    out="$(docker run --rm -v "$REPO_ROOT":/data "$IMG_PUBLICCODE" \
           -no-network /data/publiccode.yml 2>&1)"; rc=$?
else
    echo "  no publiccode-parser binary and no usable docker daemon" >&2
    exit 1
fi

[ -n "$out" ] && printf '%s\n' "$out" | sed 's/^/    /'

if [ "$rc" -ne 0 ]; then
    echo ""
    echo "  publiccode.yml has a schema error. openCode validates this file;"
    echo "  a failure here means a broken catalogue entry."
    exit 1
fi
echo "  publiccode.yml is schema-valid (warnings above, if any, are advisory)"
exit 0
