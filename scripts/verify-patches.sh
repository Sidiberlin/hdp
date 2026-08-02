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

MANIFEST_DIR="docker/patches"
APP_DIR="app"

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
MANIFEST_TSV="$(python3 - "$MANIFEST_DIR" <<'PY'
import sys, glob, os
try:
    import yaml
except ImportError:
    print("MANIFEST_ERROR: pyyaml is not installed", file=sys.stderr); sys.exit(2)

REQUIRED = ("id", "title", "class", "mode", "target", "stale", "why")
VALID_MODES = {"insert", "diff", "create", "delete"}
rows, errors, seen = [], [], set()

for f in sorted(glob.glob(os.path.join(sys.argv[1], "*.yaml"))):
    try:
        d = yaml.safe_load(open(f))
    except Exception as e:
        errors.append(f"{f}: not valid YAML: {e}"); continue
    if not isinstance(d, dict):
        errors.append(f"{f}: top level is not a mapping"); continue
    for k in REQUIRED:
        if k not in d:
            errors.append(f"{f}: missing required key '{k}'")
    if d.get("mode") not in VALID_MODES:
        errors.append(f"{f}: mode '{d.get('mode')}' is not one of {sorted(VALID_MODES)}")
    if d.get("mode") == "insert" and not d.get("marker"):
        errors.append(f"{f}: mode 'insert' requires a marker")
    if d.get("mode") == "diff" and not d.get("patch"):
        errors.append(f"{f}: mode 'diff' requires a patch path")
    pid = d.get("id")
    if pid in seen:
        errors.append(f"{f}: duplicate id '{pid}'")
    seen.add(pid)
    rows.append("\x1f".join(str(d.get(k, "")).replace("\x1f", " ").replace("\n", " ")
                for k in ("id", "class", "mode", "target", "patch", "anchor",
                          "marker", "anti", "stale", "title", "why", "applied_by")))

if errors:
    for e in errors:
        print("MANIFEST_ERROR: " + e, file=sys.stderr)
    sys.exit(2)
print("\n".join(rows))
PY
)" || { echo "${C_RED}verify-patches.sh: manifest is malformed${C_OFF}" >&2; exit 2; }

TOTAL=$(printf '%s\n' "$MANIFEST_TSV" | grep -c . || true)

# ─── Modes that only read the manifest ──────────────────────────────
if [ "$MODE" = list ]; then
    printf '%-24s %-5s %-6s %-6s %s\n' ID CLASS MODE STALE TARGET
    printf '%-24s %-5s %-6s %-6s %s\n' -- ----- ---- ----- ------
    while IFS=$'\x1f' read -r id cls mode target patch anchor marker anti stale title why applied_by; do
        [ -n "$id" ] || continue
        printf '%-24s %-5s %-6s %-6s %s\n' "$id" "$cls" "$mode" "$(echo "$stale" | tr 'A-Z' 'a-z')" "$target"
    done <<< "$MANIFEST_TSV"
    echo ""
    echo "$TOTAL patches. See docs/dev/patches.md."
    exit 0
fi

if [ "$MODE" = explain ]; then
    found=0
    while IFS=$'\x1f' read -r id cls mode target patch anchor marker anti stale title why applied_by; do
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
    while IFS=$'\x1f' read -r id cls mode target patch anchor marker anti stale title why applied_by; do
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

while IFS=$'\x1f' read -r id cls mode target patch anchor marker anti stale title why applied_by; do
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
        out="$(cd "$APP_DIR" && patch --dry-run --forward --ignore-whitespace --fuzz 3 \
               "$target" "../$patch" 2>&1)"
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
