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
#   scripts/verify-patches.sh --upgrade-report [--tree DIR]
#                                          will these patches survive an upgrade?
#
# Exit: 0 all present · 1 at least one missing · 2 malformed manifest
#       (--upgrade-report: 0 every patch evaluated and none needs work,
#        1 AMBER, RED or a patch that could not be evaluated at all)
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
    sed -n '3,20p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
}

while [ $# -gt 0 ]; do
    case "$1" in
        --static)  STATIC=1 ;;
        --list)    MODE=list ;;
        --stale)   MODE=stale ;;
        --explain) shift; MODE=explain; ONLY_ID="${1:-}" ;;
        --id)      shift; ONLY_ID="${1:-}" ;;
        --upgrade-report) MODE=upgrade ;;
        --tree)    shift; APP_DIR="${1:-}"
                   [ -n "$APP_DIR" ] || { echo "verify-patches.sh: --tree needs a directory" >&2; exit 2; } ;;
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

# ─── Upgrade report (§10.4) ─────────────────────────────────────────
# The question this mode answers is NOT the question `verify` answers, and
# confusing the two is the easiest way to misread the output:
#
#   verify           "is this patch in the tree right now?"
#                    A patch that applies cleanly means it is MISSING.
#   --upgrade-report "will this patch still work against this upstream?"
#                    A patch that applies cleanly is GREEN.
#
# Same probe, opposite reading, because the trees are different. Run this
# against a candidate upstream tree — an extracted mediawiki-1.43.9, or a
# branch where the re-vendor commit has landed and nothing has been re-applied
# yet — with `--tree DIR`. Run against the working tree it reports BLUE for
# everything, because the patches are already applied there; that is correct
# and is why the state exists.
#
#   GREEN  the patch applies cleanly. Nothing to do.
#   BLUE   the change is already in the tree. Either it is applied (working
#          tree) or upstream adopted it (fresh upstream tree) — in the latter
#          case delete the patch, the sidecar and the .diff.
#   AMBER  the target exists, but the patch neither applies nor is present.
#          Upstream moved the code. Re-derive by hand. This is the regression
#          signal, and the reason this mode exists at all.
#   RED    the target is gone, or the patch produced the wrong result. A
#          blocker: shipping this drops the patch silently.
#   N/A    the patch's whole component is absent from this tree, so nothing
#          was evaluated. Expected when --tree is a MediaWiki core tarball
#          (no BlueSpice extensions, no vendor/); on a re-vendored branch it
#          means a component got dropped. Never counted as a pass.
#
# Three of these patches target MediaWiki core, which is precisely what a core
# security release touches, and two are on the authentication path. Those are
# the rows to read first.
if [ "$MODE" = upgrade ]; then
    GREEN_N=0; AMBER_N=0; BLUE_N=0; RED_N=0; STALE_N=0; NA_N=0
    declare -a NEEDS_WORK=()
    declare -a NOT_EVALUATED=()

    echo ""
    echo "${C_BLD}upgrade report${C_OFF} ${C_DIM}— $TOTAL patches against $APP_DIR${C_OFF}"
    if [ ! -d "$APP_DIR" ]; then
        echo "  ${C_RED}$APP_DIR does not exist${C_OFF} — pass --tree <extracted upstream tree>" >&2
        exit 2
    fi
    echo ""
    printf '  %-6s %-24s %-5s %s\n' STATE ID CLASS TARGET
    printf '  %-6s %-24s %-5s %s\n' ------ ------------------------ ----- ------

    # Which component a target belongs to: an extension, a skin, a composer
    # package, or core itself. Used to tell "upstream deleted this file" from
    # "this tree does not contain that component at all" — pointing --tree at a
    # MediaWiki core tarball legitimately has no BlueSpice extensions and no
    # vendor/, and reporting eight REDs for that would drown the one row that
    # actually moved.
    component_root() {
        case "$1" in
            extensions/*/*) printf 'extensions/%s' "$(echo "${1#extensions/}" | cut -d/ -f1)" ;;
            skins/*/*)      printf 'skins/%s'      "$(echo "${1#skins/}" | cut -d/ -f1)" ;;
            vendor/*/*/*)   printf 'vendor/%s'     "$(echo "${1#vendor/}" | cut -d/ -f1,2)" ;;
            *)              printf '.' ;;
        esac
    }

    classify() {
        # Prints "STATE<tab>detail". Never mutates the tree: everything here
        # is grep or `patch --dry-run`.
        local mode="$1" target="$2" patchfile="$3" anchor="$4" marker="$5" anti="$6"
        local tpath="$APP_DIR/$target"

        if [ ! -e "$tpath" ]; then
            local root
            root="$(component_root "$target")"
            if [ ! -d "$APP_DIR/$root" ]; then
                printf 'N/A\t%s is not in this tree — nothing was evaluated for this patch' "$root"
            else
                printf 'RED\ttarget no longer exists upstream — the patch has nowhere to go'
            fi
            return
        fi
        if [ -n "$anti" ] && grep -qE -- "$anti" "$tpath" 2>/dev/null; then
            printf 'RED\tanti-pattern matches: the context changed and applying this produces the wrong result'
            return
        fi

        case "$mode" in
        insert|create)
            if [ -n "$marker" ] && grep -qE -- "$marker" "$tpath" 2>/dev/null; then
                printf 'BLUE\tmarker already present (applied here, or upstream adopted it)'
            elif [ -z "$anchor" ]; then
                printf 'AMBER\tno anchor declared, so applicability cannot be decided mechanically'
            elif grep -qE -- "$anchor" "$tpath" 2>/dev/null; then
                printf 'GREEN\tanchor present, marker absent — the insert still has its landing site'
            else
                printf 'AMBER\tanchor gone: upstream moved the code this patch attaches to'
            fi
            ;;
        diff)
            local resolved="$patchfile"
            if [ ! -f "$resolved" ] && [ -f "$MANIFEST_DIR/$(basename "$resolved")" ]; then
                resolved="$MANIFEST_DIR/$(basename "$resolved")"
            fi
            if [ ! -f "$resolved" ]; then
                printf 'RED\tthe .diff itself is missing from the tree (%s)' "$patchfile"
                return
            fi
            if ! command -v patch >/dev/null 2>&1; then
                printf 'AMBER\tpatch(1) is not installed, so nothing could be decided'
                return
            fi
            # Absolute path before the cd, for the reason recorded in the
            # verify loop below: resolving it inside the subshell resolves it
            # relative to app/ and turns every Class-C row into a false GREEN.
            local abs out
            abs="$(cd "$(dirname "$resolved")" && pwd)/$(basename "$resolved")"
            out="$(cd "$APP_DIR" && patch --dry-run --forward --ignore-whitespace --fuzz 3 \
                   "$target" "$abs" 2>&1)"
            if printf '%s' "$out" | grep -qi 'previously applied\|Reversed'; then
                printf 'BLUE\talready in the file (applied here, or upstream adopted it)'
            elif printf '%s' "$out" | grep -qi 'FAILED\|malformed\|misordered'; then
                printf 'AMBER\tdoes not apply: %s' \
                    "$(printf '%s' "$out" | grep -i 'FAILED\|malformed\|misordered' | head -1 | sed 's/^[[:space:]]*//')"
            else
                printf 'GREEN\tapplies cleanly%s' \
                    "$(printf '%s' "$out" | grep -qi 'fuzz\|offset' && echo ' (with fuzz/offset — re-derive when convenient)' || true)"
            fi
            ;;
        delete)
            printf 'GREEN\tdeletion patch; nothing upstream can move'
            ;;
        *)
            printf 'AMBER\tunhandled mode %s' "$mode"
            ;;
        esac
    }

    # shellcheck disable=SC2034
    while IFS=$'\x1f' read -r id cls mode target patch anchor marker anti stale title why applied_by group upstream_version hunks regex; do
        [ -n "$id" ] || continue
        if [ -n "$ONLY_ID" ] && [ "$id" != "$ONLY_ID" ]; then continue; fi

        if [ "$stale" = "true" ] || [ "$stale" = "True" ]; then
            # A stale patch has no upstream to survive. The one thing worth
            # reporting is the opposite case: the target coming BACK, which
            # means the entry is now wrong.
            if [ -e "$APP_DIR/$target" ]; then
                printf '  %sRED%s    %-24s %-5s %s\n' "$C_RED" "$C_OFF" "$id" "$cls" "$target"
                printf '         %sdeclared stale, but the target exists in this tree — re-derive and clear stale%s\n' "$C_DIM" "$C_OFF"
                RED_N=$((RED_N+1)); NEEDS_WORK+=("$id")
            else
                printf '  %sSTALE%s  %-24s %-5s %s\n' "$C_DIM" "$C_OFF" "$id" "$cls" "$target"
                STALE_N=$((STALE_N+1))
            fi
            continue
        fi

        result="$(classify "$mode" "$target" "$patch" "$anchor" "$marker" "$anti")"
        state="${result%%$'\t'*}"
        detail="${result#*$'\t'}"

        case "$state" in
            GREEN) colour="$C_GRN"; GREEN_N=$((GREEN_N+1)) ;;
            BLUE)  colour="$C_DIM"; BLUE_N=$((BLUE_N+1)) ;;
            AMBER) colour="$C_YEL"; AMBER_N=$((AMBER_N+1)); NEEDS_WORK+=("$id") ;;
            RED)   colour="$C_RED"; RED_N=$((RED_N+1));   NEEDS_WORK+=("$id") ;;
            N/A)   colour="$C_YEL"; NA_N=$((NA_N+1));     NOT_EVALUATED+=("$id") ;;
            *)     colour="$C_RED"; RED_N=$((RED_N+1));   NEEDS_WORK+=("$id") ;;
        esac
        printf '  %s%-6s%s %-24s %-5s %s\n' "$colour" "$state" "$C_OFF" "$id" "$cls" "$target"
        [ "$state" = GREEN ] || printf '         %s%s%s\n' "$C_DIM" "$detail" "$C_OFF"
    done <<< "$MANIFEST_TSV"

    echo ""
    printf '  %s%d GREEN%s  %s%d BLUE%s  %s%d AMBER%s  %s%d RED%s' \
        "$C_GRN" "$GREEN_N" "$C_OFF" "$C_DIM" "$BLUE_N" "$C_OFF" \
        "$C_YEL" "$AMBER_N" "$C_OFF" "$C_RED" "$RED_N" "$C_OFF"
    [ "$NA_N"    -gt 0 ] && printf '  %s%d not evaluated%s' "$C_YEL" "$NA_N" "$C_OFF"
    [ "$STALE_N" -gt 0 ] && printf '  %s%d stale%s' "$C_DIM" "$STALE_N" "$C_OFF"
    printf '   of %s\n' "$TOTAL"
    echo ""

    if [ "$NA_N" -gt 0 ]; then
        # A skip is not a pass. This tree does not contain those components, so
        # the report says nothing about those patches — which is a partial
        # answer, and the exit code has to reflect that.
        echo "  ${C_YEL}${#NOT_EVALUATED[@]} patch(es) were NOT evaluated${C_OFF} — their component is absent from"
        echo "  this tree: ${NOT_EVALUATED[*]}"
        echo ""
        echo "  That is expected when --tree points at a MediaWiki core tarball, which"
        echo "  ships neither the BlueSpice extensions nor app/vendor/. It is NOT"
        echo "  expected on a re-vendored branch: there, a missing component is a"
        echo "  component that got dropped. Re-run against the full candidate tree"
        echo "  before believing the GREEN rows are the whole story."
        echo ""
    fi

    if [ "$AMBER_N" -gt 0 ] || [ "$RED_N" -gt 0 ] || [ "$NA_N" -gt 0 ]; then
      if [ "${#NEEDS_WORK[@]}" -gt 0 ]; then
        echo "  ${C_BLD}${#NEEDS_WORK[@]} patch(es) need a human before this upgrade ships:${C_OFF}"
        echo "    ${NEEDS_WORK[*]}"
        echo ""
        echo "  AMBER — re-derive the .diff against the new upstream, then update the"
        echo "          sidecar's anchor/marker and upstream_version. One commit each,"
        echo "          so a bad re-derivation is revertible on its own."
        echo "  RED   — blocker. Either the file is gone (decide: stale, or the patch"
        echo "          moves to its new location) or applying it produces the wrong"
        echo "          result. Do not ship either."
        echo ""
        echo "  scripts/verify-patches.sh --explain <id>   what the patch is for"
        echo "  docs/dev/upgrade-runbook.md                step 4, TRIAGE"
        echo ""
      fi
      exit 1
    fi

    echo "  Nothing to re-derive. All patches either apply cleanly or are already in"
    echo "  the tree. Re-run after the re-vendor commit, not before it."
    echo ""
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
