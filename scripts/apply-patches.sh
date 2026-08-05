#!/usr/bin/env bash
# ============================================================
# apply-patches.sh — put the patches back.
#
# The mutating half of the pair. verify-patches.sh tells you a patch is gone;
# this puts it back. Driven by the same docker/patches/*.yaml manifest, so the
# two cannot disagree about what a patch is.
#
#   scripts/apply-patches.sh              apply every applicable patch
#   scripts/apply-patches.sh --id es-ssl  one patch
#   scripts/apply-patches.sh --class A    only composer-clobbered patches
#   scripts/apply-patches.sh --dry-run    say what would change, change nothing
#   scripts/apply-patches.sh --list       the inventory
#
# Failure policy is warn-then-fail-at-end, and it is deliberate. docker/setup.sh
# calls this under `set -euo pipefail`, so exiting the moment one patch does not
# apply would abort the whole install: an upstream reindent that breaks a single
# anchor would leave every operator with a half-installed wiki and no database.
# That is strictly worse than today's silent no-op. So every patch is attempted,
# each failure is reported in full, and the non-zero exit comes at the very end
# — the user gets a working wiki *and* an accurate account of what is wrong
# with it.
#
# Uses `patch --ignore-whitespace --fuzz 3`, never `git apply`. git apply has no
# fuzz factor, and inside the mediawiki container /var/www/html/w is a bind
# mount with no .git, so git apply changes behaviour between host and container.
# 99-apply_patches.sh already uses patch --fuzz 3; two incompatible fuzz
# policies in one repository is worse than either.
#
# Exit: 0 everything applied (or already present) · 1 at least one failed
#       2 bad usage or malformed manifest
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT" || exit 2

# Overridable so this can run inside the mediawiki container, where only the
# MediaWiki tree and these two directories are mounted — there is no repo root.
MANIFEST_DIR="${HDP_PATCH_MANIFEST_DIR:-docker/patches}"
APP_DIR="${HDP_APP_DIR:-app}"
LIB_DIR="${HDP_PATCH_LIB_DIR:-$(dirname "${BASH_SOURCE[0]}")/lib}"

if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
    C_RED=$'\033[0;31m'; C_GRN=$'\033[0;32m'; C_YEL=$'\033[0;33m'
    C_DIM=$'\033[0;90m'; C_BLD=$'\033[1m'; C_OFF=$'\033[0m'
else
    C_RED=''; C_GRN=''; C_YEL=''; C_DIM=''; C_BLD=''; C_OFF=''
fi

ONLY_ID=""; ONLY_CLASS=""; DRY=0; MODE=apply

usage() { sed -n '3,30p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; }

while [ $# -gt 0 ]; do
    case "$1" in
        --id)      shift; ONLY_ID="${1:-}" ;;
        --class)   shift; ONLY_CLASS="${1:-}" ;;
        --dry-run) DRY=1 ;;
        --list)    MODE=list ;;
        --help|-h) usage; exit 0 ;;
        *) echo "apply-patches.sh: unknown option '$1' (try --help)" >&2; exit 2 ;;
    esac
    shift
done

command -v patch >/dev/null 2>&1 || { echo "apply-patches.sh: patch(1) is required" >&2; exit 2; }
command -v python3 >/dev/null 2>&1 || { echo "apply-patches.sh: python3 is required" >&2; exit 2; }

MANIFEST="$(python3 "$LIB_DIR/read-manifest.py" "$MANIFEST_DIR")" || exit 2

if [ "$MODE" = list ]; then
    printf '%-22s %-5s %-6s %s\n' ID CLASS MODE TARGET
    # The read list must name every field read-manifest.py emits, in order, or
# bash assigns the remainder to the last variable and every record silently
# shifts. Several are unused here by design.
# shellcheck disable=SC2034
while IFS=$'\x1f' read -r id cls mode target patch anchor marker anti stale title why applied_by group upstream_version hunks regex; do
        [ -n "$id" ] && printf '%-22s %-5s %-6s %s\n' "$id" "$cls" "$mode" "$target"
    done <<< "$MANIFEST"
    exit 0
fi

APPLIED=0; ALREADY=0; SKIPPED=0; FAILED=0
declare -a FAILED_IDS=()

fail_patch() {   # id title target reason cause fix
    echo ""
    echo "  ${C_RED}✗ $1${C_OFF} — $2"
    echo "    target : app/$3  ($([ -e "$APP_DIR/$3" ] && echo exists || echo MISSING))"
    echo "    failed : $4"
    echo "    cause  : $5"
    echo "    fix    : $6"
    echo "    docs   : docs/dev/patches.md"
    # Machine-readable, for docker/setup.sh. It used to recover the failed ids
    # by grepping this block's `  ✗ <id> —` line, which does not survive a
    # C/POSIX locale: `✗` is three UTF-8 bytes and ERE `.` matches one byte, so
    # in the wikimedia php-fpm image (which sets no locale) the pattern never
    # matched and every real per-patch failure degraded to the generic
    # "failed before it could report per-patch results" — losing the patch ids,
    # which is the only part an operator can act on.
    #
    # Anchored at column 0 and pure ASCII on purpose: no locale, no byte-width
    # and no colour escape can come between a caller and the id.
    echo "HDP_PATCH_FAILED=$1"
    FAILED=$((FAILED+1)); FAILED_IDS+=("$1")
}

echo ""
echo "${C_BLD}apply-patches${C_OFF}${DRY:+ }$([ "$DRY" = 1 ] && echo "${C_YEL}(dry run)${C_OFF}")"
echo ""

# The read list must name every field read-manifest.py emits, in order, or
# bash assigns the remainder to the last variable and every record silently
# shifts. Several are unused here by design.
# shellcheck disable=SC2034
while IFS=$'\x1f' read -r id cls mode target patch anchor marker anti stale title why applied_by group upstream_version hunks regex; do
    [ -n "$id" ] || continue
    [ -n "$ONLY_ID" ]    && [ "$id"  != "$ONLY_ID" ]    && continue
    [ -n "$ONLY_CLASS" ] && [ "$cls" != "$ONLY_CLASS" ] && continue

    tpath="$APP_DIR/$target"

    if [ "$stale" = "true" ] || [ "$stale" = "True" ]; then
        echo "  ${C_DIM}skip${C_OFF}   $id ${C_DIM}(declared stale; target gone upstream)${C_OFF}"
        SKIPPED=$((SKIPPED+1)); continue
    fi

    if [ ! -e "$tpath" ]; then
        # Under app/vendor/ this is expected before composer has run, and is
        # not a failure: 99-apply_patches.sh applies those after composer
        # recreates the tree.
        case "$target" in
            vendor/*)
                echo "  ${C_DIM}skip${C_OFF}   $id ${C_DIM}(app/vendor/ not built yet)${C_OFF}"
                SKIPPED=$((SKIPPED+1)); continue ;;
        esac
        fail_patch "$id" "$title" "$target" "target does not exist" \
            "the extension is not installed, or upstream removed the file" \
            "if upstream removed it, set 'stale: true' in $MANIFEST_DIR/$id.yaml"
        continue
    fi

    if [ -z "$patch" ]; then
        fail_patch "$id" "$title" "$target" "no patch file in the manifest" \
            "the sidecar has no 'patch:' field" \
            "add one, or apply this patch by hand"
        continue
    fi
    # Resolve the manifest's repo-relative patch path for whichever layout we
    # are in. On the host that is the path as written; inside the mediawiki
    # container there is no repo root, the manifest is at $HDP_PATCH_MANIFEST_DIR
    # and the MediaWiki tree at $HDP_APP_DIR, so "app/..." and
    # "docker/patches/..." both have to be remapped.
    if [ ! -f "$patch" ]; then
        case "$patch" in
            app/*)  [ -f "$APP_DIR/${patch#app/}" ] && patch="$APP_DIR/${patch#app/}" ;;
        esac
    fi
    if [ ! -f "$patch" ] && [ -f "$MANIFEST_DIR/$(basename "$patch")" ]; then
        patch="$MANIFEST_DIR/$(basename "$patch")"
    fi
    if [ ! -f "$patch" ]; then
        fail_patch "$id" "$title" "$target" "patch file $patch is missing" \
            "the .patch/.diff itself is not in the tree" "restore $patch"
        continue
    fi

    # Already applied? --forward makes patch say so rather than prompt.
    # The manifest stores repo-relative patch paths. Resolve to an absolute
    # path once, so `cd "$APP_DIR"` below cannot break them regardless of
    # whether we are in the repo or in the container.
    PATCH_ABS="$(cd "$(dirname "$patch")" && pwd)/$(basename "$patch")"

    # `hunks` used to be read and never used (both scripts carried a shellcheck
    # disable for it). Assert it against the patch file itself: a re-derive that
    # drops a hunk is otherwise invisible, because every remaining hunk still
    # applies and every check downstream still passes. This is the static half —
    # it holds even for patches that are already applied, and it fails on the
    # manifest, which is where the disagreement actually is.
    if [ -n "$hunks" ]; then
        declared_hunks="$(printf '%s' "$hunks" | tr -cd '0-9')"
        actual_hunks="$(grep -c '^@@' "$PATCH_ABS" 2>/dev/null || echo 0)"
        if [ -n "$declared_hunks" ] && [ "$declared_hunks" != "$actual_hunks" ]; then
            fail_patch "$id" "$title" "$target" \
                "manifest declares $declared_hunks hunks, $patch contains $actual_hunks" \
                "the patch was re-derived and gained or lost a hunk, or the manifest was not updated with it" \
                "reconcile 'hunks:' in $MANIFEST_DIR/$id.yaml with $patch — a dropped hunk still applies cleanly"
            continue
        fi
    fi

    probe="$(cd "$APP_DIR" && patch --dry-run --forward --ignore-whitespace --fuzz 3 \
             "$target" "$PATCH_ABS" 2>&1)"

    # FAILED is tested first, and the order is load-bearing. A probe can report
    # both — "Reversed (or previously applied)" for the hunks already in the
    # file and a failure for one that is not — and the old order let the
    # already-applied branch win, which reported `ok (already present)` for a
    # half-applied patch and skipped every check below.
    if printf '%s' "$probe" | grep -qi 'FAILED\|malformed\|misordered'; then
        fail_patch "$id" "$title" "$target" "patch does not apply, even with --fuzz 3" \
            "upstream moved the code this patch is written against" \
            "re-derive $patch against the current upstream, then update $MANIFEST_DIR/$id.yaml"
        continue
    fi
    if printf '%s' "$probe" | grep -qi 'previously applied\|Reversed'; then
        # "Already applied" is a claim about the file, so check the file. patch
        # says "Reversed (or previously applied)" whenever the hunks it can see
        # are already there — including when only *some* of them are, in which
        # case it reports "2 out of 2 hunks ignored" and exits as though nothing
        # were wrong. Measured against a tree with hunk 2 of
        # maps-layercontrol-xss-js reverted out: the probe said previously
        # applied, and this branch reported ok, for a half-fixed stored XSS.
        #
        # marker present and anti absent is the same pair verify-patches.sh
        # asserts. Doing it here too is what makes the applier's `ok` mean the
        # same thing as the verifier's.
        if [ -n "$marker" ] && ! grep -qE -- "$marker" "$tpath" 2>/dev/null; then
            fail_patch "$id" "$title" "$target" "patch reports already applied but the marker is absent" \
                "the file matches the patch context without carrying its result — upstream may have changed it independently" \
                "inspect app/$target against $patch by hand"
            continue
        fi
        if [ -n "$anti" ] && grep -qE -- "$anti" "$tpath" 2>/dev/null; then
            fail_patch "$id" "$title" "$target" "patch reports already applied but the anti-pattern still matches" \
                "the patch is only partly present — at least one hunk did not land" \
                "re-apply $patch by hand and check every hunk; do not ship this"
            continue
        fi
        echo "  ${C_GRN}ok${C_OFF}     $id ${C_DIM}(already present)${C_OFF}"
        ALREADY=$((ALREADY+1)); continue
    fi

    if [ "$DRY" = 1 ]; then
        echo "  ${C_YEL}would${C_OFF}  $id ${C_DIM}(applies cleanly)${C_OFF}"
        APPLIED=$((APPLIED+1)); continue
    fi

    if (cd "$APP_DIR" && patch --silent --ignore-whitespace --fuzz 3 "$target" "$PATCH_ABS") ; then
        # Do not trust the exit code alone: confirm the marker landed where the
        # manifest says it should. This is the same silent-no-op class the sed
        # blocks suffered from, one layer up.
        if [ -n "$marker" ] && ! grep -qE -- "$marker" "$tpath" 2>/dev/null; then
            fail_patch "$id" "$title" "$target" "patch reported success but the marker is absent" \
                "the patch applied into changed context and produced the wrong result" \
                "re-derive $patch; do not ship this"
            continue
        fi
        # And the anti-pattern must be gone. On a multi-hunk patch the marker
        # only witnesses the hunk it lives in, so marker-alone would call a
        # partial apply a success — see the sidecar for maps-layercontrol-xss-js,
        # where the marker is in hunk 1 and the second XSS sink is in hunk 2.
        if [ -n "$anti" ] && grep -qE -- "$anti" "$tpath" 2>/dev/null; then
            fail_patch "$id" "$title" "$target" "patch reported success but the anti-pattern still matches" \
                "at least one hunk did not produce its result — the marker only witnesses the hunk it is in" \
                "re-derive $patch; do not ship this"
            continue
        fi
        echo "  ${C_GRN}applied${C_OFF} $id"
        APPLIED=$((APPLIED+1))
    else
        fail_patch "$id" "$title" "$target" "patch(1) returned non-zero" \
            "the patch could not be applied to this file" \
            "inspect app/$target and $patch by hand"
    fi
done <<< "$MANIFEST"

echo ""
printf '  %s%d applied%s, %s%d already present%s' "$C_GRN" "$APPLIED" "$C_OFF" "$C_GRN" "$ALREADY" "$C_OFF"
[ "$SKIPPED" -gt 0 ] && printf ', %s%d skipped%s' "$C_DIM" "$SKIPPED" "$C_OFF"
[ "$FAILED"  -gt 0 ] && printf ', %s%d FAILED%s' "$C_RED" "$FAILED" "$C_OFF"
echo ""

if [ "$FAILED" -gt 0 ]; then
    echo ""
    echo "  ${C_RED}$FAILED patch(es) could not be applied.${C_OFF} The wiki will run without"
    echo "  them. Investigate with:"
    echo "    scripts/verify-patches.sh --explain ${FAILED_IDS[0]}"
    echo ""
    exit 1
fi
echo ""
exit 0
