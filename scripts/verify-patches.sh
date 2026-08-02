#!/usr/bin/env bash
# ============================================================
# verify-patches.sh — is every patch still in the tree?
#
# Reads docker/patches/*.yaml and checks each entry against the working tree.
# Never mutates anything: this is the gate, not the applier. Run it after
# composer, which is when patches disappear.
#
#   scripts/verify-patches.sh              verify every patch
#   scripts/verify-patches.sh --static     schema and target paths only, no patch(1)
#   scripts/verify-patches.sh --id es-ssl  one patch
#   scripts/verify-patches.sh --list       the inventory
#   scripts/verify-patches.sh --explain ID why this patch exists
#   scripts/verify-patches.sh --stale      report patches that can never apply
#
# Exit: 0 all present · 1 at least one missing · 2 malformed manifest
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT" || exit 2

MANIFEST_DIR="${HDP_PATCH_MANIFEST_DIR:-docker/patches}"
APP_DIR="${HDP_APP_DIR:-app}"
LIB_DIR="${HDP_PATCH_LIB_DIR:-$(dirname "${BASH_SOURCE[0]}")/lib}"

if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
    C_RED=$'\033[0;31m'; C_GRN=$'\033[0;32m'; C_YEL=$'\033[0;33m'
    C_DIM=$'\033[0;90m'; C_BLD=$'\033[1m'; C_OFF=$'\033[0m'
else
    C_RED=''; C_GRN=''; C_YEL=''; C_DIM=''; C_BLD=''; C_OFF=''
fi

MODE=verify
ONLY_ID=""
STATIC=0

usage() {
    sed -n '3,17p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
}

while [ $# -gt 0 ]; do
    case "$1" in
        --static)  STATIC=1 ;;
        --list)    MODE=list ;;
        --stale)   MODE=stale ;;
        --explain) shift; MODE=explain; ONLY_ID="${1:-}" ;;
        --id)      shift; ONLY_ID="${1:-}" ;;
        --help|-h) usage; exit 0 ;;
        *) echo "verify-patches.sh: unknown option '$1' (try --help)" >&2; exit 2 ;;
    esac
    shift
done

command -v python3 >/dev/null 2>&1 || { echo "verify-patches.sh: python3 is required to read the manifest" >&2; exit 2; }

[ -d "$MANIFEST_DIR" ] || { echo "verify-patches.sh: $MANIFEST_DIR does not exist" >&2; exit 2; }

# ─── Load and schema-check the manifest ─────────────────────────────
# Emits one TSV record per patch. Any schema problem is exit 2 — a malformed
# manifest is a different failure from a missing patch and must not be
# mistaken for one.
MANIFEST_TSV="$(python3 "$LIB_DIR/read-manifest.py" "$MANIFEST_DIR")" || { echo "${C_RED}verify-patches.sh: manifest is malformed${C_OFF}" >&2; exit 2; }

TOTAL=$(printf '%s\n' "$MANIFEST_TSV" | grep -c . || true)

# ─── Modes that only read the manifest ──────────────────────────────
if [ "$MODE" = list ]; then
    printf '%-24s %-5s %-6s %-6s %s\n' ID CLASS MODE STALE TARGET
    printf '%-24s %-5s %-6s %-6s %s\n' -- ----- ---- ----- ------
    # The read list must name every field read-manifest.py emits, in order, or
# bash assigns the remainder to the last variable and every record silently
# shifts. Several are unused here by design.
# shellcheck disable=SC2034
while IFS=$'\x1f' read -r id cls mode target patch anchor marker anti stale title why applied_by group upstream_version hunks regex; do
        [ -n "$id" ] || continue
        printf '%-24s %-5s %-6s %-6s %s\n' "$id" "$cls" "$mode" "$(echo "$stale" | tr 'A-Z' 'a-z')" "$target"
    done <<< "$MANIFEST_TSV"
    echo ""
    echo "$TOTAL patches. See docs/dev/patches.md."
    exit 0
fi

if [ "$MODE" = explain ]; then
    found=0
    # The read list must name every field read-manifest.py emits, in order, or
# bash assigns the remainder to the last variable and every record silently
# shifts. Several are unused here by design.
# shellcheck disable=SC2034
while IFS=$'\x1f' read -r id cls mode target patch anchor marker anti stale title why applied_by group upstream_version hunks regex; do
        [ "$id" = "$ONLY_ID" ] || continue
        found=1
        echo ""
        echo "${C_BLD}$id${C_OFF} — $title"
        echo "  class      : $cls   mode: $mode   stale: $stale"
        echo "  target     : app/$target"
        [ -n "$patch" ]  && echo "  patch      : $patch"
        [ -n "$anchor" ] && echo "  anchor     : $anchor"
        [ -n "$marker" ] && echo "  marker     : $marker"
        echo "  applied by : $applied_by"
        echo "  why        : $why"
        echo ""
    done <<< "$MANIFEST_TSV"
    [ "$found" = 1 ] || { echo "no patch with id '$ONLY_ID' (try --list)" >&2; exit 2; }
    exit 0
fi

if [ "$MODE" = stale ]; then
    n=0
    # The read list must name every field read-manifest.py emits, in order, or
# bash assigns the remainder to the last variable and every record silently
# shifts. Several are unused here by design.
# shellcheck disable=SC2034
while IFS=$'\x1f' read -r id cls mode target patch anchor marker anti stale title why applied_by group upstream_version hunks regex; do
        [ "$stale" = "True" ] || [ "$stale" = "true" ] || continue
        n=$((n+1))
        echo "${C_YEL}$id${C_OFF} — $title"
        echo "  target app/$target  ($([ -e "$APP_DIR/$target" ] && echo 'still present!' || echo 'gone upstream'))"
        echo "  $why"
    done <<< "$MANIFEST_TSV"
    echo ""
    echo "$n stale patch(es) of $TOTAL."
    exit 0
fi

# ─── Verify ─────────────────────────────────────────────────────────
PRESENT=0; MISSING=0; STALE=0; SKIPPED=0
declare -a FAILED_IDS=()

# The §6.3 failure-message contract. Unspecified messages become one useless
# line; this one always names the target, the reason, the cause and the fix.
report_missing() {
    local id="$1" title="$2" target="$3" reason="$4" pattern="$5" cause="$6" fix="$7"
    echo ""
    echo "${C_RED}✗ $id${C_OFF} — $title"
    echo "  target : app/$target  ($([ -e "$APP_DIR/$target" ] && echo exists || echo MISSING))"
    echo "  failed : $reason"
    [ -n "$pattern" ] && echo "  regex  : $pattern        (ERE)"
    echo "  cause  : $cause"
    echo "  fix    : $fix"
    echo "  docs   : docs/dev/patches.md"
    FAILED_IDS+=("$id")
    MISSING=$((MISSING+1))
}

echo ""
echo "${C_BLD}verify-patches${C_OFF} ${C_DIM}— $TOTAL patches from $MANIFEST_DIR${C_OFF}"
echo ""

# The read list must name every field read-manifest.py emits, in order, or
# bash assigns the remainder to the last variable and every record silently
# shifts. Several are unused here by design.
# shellcheck disable=SC2034
while IFS=$'\x1f' read -r id cls mode target patch anchor marker anti stale title why applied_by group upstream_version hunks regex; do
    [ -n "$id" ] || continue
    if [ -n "$ONLY_ID" ] && [ "$id" != "$ONLY_ID" ]; then continue; fi

    tpath="$APP_DIR/$target"

    # Declared stale: the target is gone upstream and the patch can never
    # apply. Reported, never fatal — see docs/dev/patches.md for why an
    # always-failing gate is worse than none.
    if [ "$stale" = "true" ] || [ "$stale" = "True" ]; then
        if [ -e "$tpath" ]; then
            # Upstream restored the file: the entry is now wrong, not the tree.
            echo "  ${C_YEL}stale?${C_OFF} $id — declared stale but app/$target now EXISTS."
            echo "          Re-derive the patch and clear 'stale' in the manifest."
        else
            echo "  ${C_DIM}stale${C_OFF}  $id ${C_DIM}(target gone upstream; expected)${C_OFF}"
        fi
        STALE=$((STALE+1))
        continue
    fi

    if [ "$STATIC" = 1 ]; then
        # Schema is already validated above; --static additionally asserts
        # every non-stale target path exists. No patch(1), no composer, ~0s.
        #
        # Targets under app/vendor/ are exempt: that tree is gitignored on
        # purpose and composer recreates it, so before the first install the
        # file genuinely does not exist and its absence says nothing about the
        # patch. --static is the mode that runs on a fresh clone (it is what
        # manifest-lint and check.sh call), so without this it reported a false
        # failure to every contributor. The full verification still covers
        # these once composer has run.
        case "$target" in
            vendor/*)
                echo "  ${C_DIM}n/a${C_OFF}    $id ${C_DIM}(under app/vendor/, created by composer)${C_OFF}"
                SKIPPED=$((SKIPPED+1))
                continue
                ;;
        esac
        if [ -e "$tpath" ]; then
            echo "  ${C_GRN}ok${C_OFF}     $id ${C_DIM}(target present)${C_OFF}"
            PRESENT=$((PRESENT+1))
        else
            report_missing "$id" "$title" "$target" "target does not exist" "" \
                "the file the patch applies to is not in the tree" \
                "check the extension is installed, or mark the patch stale"
        fi
        continue
    fi

    case "$mode" in
    insert|create)
        if [ ! -e "$tpath" ]; then
            report_missing "$id" "$title" "$target" "target does not exist" "$marker" \
                "the file the patch applies to is not in the tree" \
                "bash scripts/apply-patches.sh --id $id"
            continue
        fi
        if grep -qE -- "$marker" "$tpath" 2>/dev/null; then
            # anti must NOT match once the patch is in place.
            if [ -n "$anti" ] && grep -qE -- "$anti" "$tpath" 2>/dev/null; then
                report_missing "$id" "$title" "$target" "marker present but anti-pattern also matched" "$anti" \
                    "the patch applied into changed context and produced the wrong result" \
                    "re-derive the patch; do not ship this"
            else
                echo "  ${C_GRN}ok${C_OFF}     $id"
                PRESENT=$((PRESENT+1))
            fi
        else
            report_missing "$id" "$title" "$target" "marker not found" "$marker" \
                "composer reinstalled the package as a dist archive and overwrote the patch" \
                "bash scripts/apply-patches.sh --id $id"
        fi
        ;;
    diff)
        if [ ! -e "$tpath" ]; then
            report_missing "$id" "$title" "$target" "target does not exist" "" \
                "the extension is not installed, or upstream removed the file" \
                "if upstream removed it, set 'stale: true' in $MANIFEST_DIR/$id.yaml"
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
            report_missing "$id" "$title" "$target" "patch file $patch is missing" "" \
                "the .diff itself is not in the tree" \
                "restore $patch"
            continue
        fi
        if ! command -v patch >/dev/null 2>&1; then
            echo "  ${C_YEL}skip${C_OFF}   $id ${C_DIM}(patch(1) not installed)${C_OFF}"
            SKIPPED=$((SKIPPED+1)); continue
        fi
        # --forward makes an already-applied patch report "previously applied"
        # instead of prompting. Same flags 99-apply_patches.sh uses, so a
        # disagreement here is a real disagreement.
        # Resolve the patch to an absolute path BEFORE cd'ing into $APP_DIR.
        # Doing it inside the subshell resolves it relative to app/, which
        # silently turned every Class-C check into "patch still applies" — i.e.
        # 16 false MISSINGs.
        patch_abs="$(cd "$(dirname "$patch")" && pwd)/$(basename "$patch")"
        out="$(cd "$APP_DIR" && patch --dry-run --forward --ignore-whitespace --fuzz 3 \
               "$target" "$patch_abs" 2>&1)"
        if printf '%s' "$out" | grep -qi 'previously applied\|Reversed'; then
            echo "  ${C_GRN}ok${C_OFF}     $id"
            PRESENT=$((PRESENT+1))
        elif printf '%s' "$out" | grep -qi 'FAILED\|malformed\|misordered'; then
            report_missing "$id" "$title" "$target" "patch neither applied nor applicable" "" \
                "upstream moved the code this patch is written against" \
                "re-derive $patch against the current upstream"
        else
            report_missing "$id" "$title" "$target" "patch still applies cleanly, so it is NOT in the tree" "" \
                "composer or an upstream refresh overwrote the patched file" \
                "composer dump-autoload re-runs 99-apply_patches.sh, or apply $patch by hand"
        fi
        ;;
    delete)
        if [ -n "$anti" ] && grep -qE -- "$anti" "$tpath" 2>/dev/null; then
            report_missing "$id" "$title" "$target" "content that should have been deleted is present" "$anti" \
                "the deletion did not run, or the file was restored" \
                "bash scripts/apply-patches.sh --id $id"
        else
            echo "  ${C_GRN}ok${C_OFF}     $id"
            PRESENT=$((PRESENT+1))
        fi
        ;;
    *)
        echo "  ${C_RED}??${C_OFF}     $id — unhandled mode '$mode'"
        MISSING=$((MISSING+1)); FAILED_IDS+=("$id")
        ;;
    esac
done <<< "$MANIFEST_TSV"

# ─── Verdict ────────────────────────────────────────────────────────
echo ""
printf '  %s%d present%s' "$C_GRN" "$PRESENT" "$C_OFF"
[ "$STALE"   -gt 0 ] && printf ', %s%d stale%s'   "$C_DIM" "$STALE"   "$C_OFF"
[ "$SKIPPED" -gt 0 ] && printf ', %s%d skipped%s' "$C_YEL" "$SKIPPED" "$C_OFF"
[ "$MISSING" -gt 0 ] && printf ', %s%d MISSING%s' "$C_RED" "$MISSING" "$C_OFF"
printf '   of %s\n' "$TOTAL"

if [ "$MISSING" -gt 0 ]; then
    echo ""
    echo "  ${C_RED}Patches are missing from the tree.${C_OFF} Ship this and the wiki runs"
    echo "  without them, silently. Re-run one with:"
    echo "    scripts/verify-patches.sh --id ${FAILED_IDS[0]}"
    echo "    scripts/verify-patches.sh --explain ${FAILED_IDS[0]}"
    echo ""
    exit 1
fi
echo ""
exit 0
