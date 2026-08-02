#!/usr/bin/env bash
# ============================================================
# T0 — no patch target may be gitignored.
#
# This is the cheap gate for the failure class docs/QA-REPORT.md calls Bug 2: a
# file that exists on the maintainer's disk, is required at runtime, and is
# silently un-addable because a .gitignore rule matches it. `git add -A` skips
# it without a word. Nothing looks wrong until a fresh clone.
#
# It has already happened twice in this repository. The Vector HookRunner.php
# fix was lost exactly this way. app/composer.lock and app/composer.local.json
# were tracked while still matched by an ignore rule until the fresh-clone gate
# found them.
#
# `git check-ignore` alone is not enough: by default it reports nothing for a
# tracked path, so a file that is both tracked and ignored — the dangerous
# state — looks clean. --no-index is what makes the check meaningful.
#
# Targets are derived from the tree rather than hardcoded, so a patch added
# without touching this file is still covered. Once the Wave 1.4 manifest
# exists this reads its `target:` fields instead.
#
# Exit: 0 all targets trackable · 1 at least one is ignored
# ============================================================
set -uo pipefail

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] || { echo "  not inside a git repository"; exit 1; }
cd "$REPO_ROOT" || exit 1

# Class A — clobbered by composer, re-applied by docker/setup.sh.
TARGETS=(
    app/extensions/BlueSpiceExtendedSearch/src/Backend.php
    app/extensions/BlueSpiceExtendedSearch/resources/ext.blueSpiceExtendedSearch.SearchCenter.js
)

# Class C — one target per inherited BlueSpice .diff. Derived, not listed:
# app/_bluespice/patches/<path>.diff patches app/<path>.
while IFS= read -r diff; do
    t="${diff#app/_bluespice/patches/}"
    TARGETS+=("app/${t%.diff}")
done < <(find app/_bluespice/patches -type f -name '*.diff' 2>/dev/null | sort)

# The patch sources themselves must also stay trackable — an ignored .diff is
# a patch that vanishes from the next clone.
while IFS= read -r diff; do
    TARGETS+=("$diff")
done < <(find app/_bluespice/patches -type f -name '*.diff' 2>/dev/null | sort)

fails=0
checked=0
exempt=0
for t in "${TARGETS[@]}"; do
    # app/vendor/ is ignored on purpose. It was untracked deliberately so a
    # fresh clone still runs composer, and composer recreates it on every
    # install. The one patch target under it —
    # vendor/jumbojett/openid-connect-php — is re-applied by
    # 99-apply_patches.sh *after* composer writes the file, so it is never
    # committed and being ignored is the correct state, not a defect.
    #
    # This does mean that patch has no gitignore-level protection. What
    # protects it is that it is re-applied on every single install, and the
    # patch-integrity tier asserts it afterwards. It is also the
    # security-critical one, so it is verified explicitly rather than assumed.
    case "$t" in
        app/vendor/*) exempt=$((exempt + 1)); continue ;;
    esac
    checked=$((checked + 1))
    if rule="$(git check-ignore --no-index -v "$t" 2>/dev/null)"; then
        echo "  IGNORED  $t"
        echo "           by $rule"
        fails=$((fails + 1))
    fi
done

if [ "$fails" -gt 0 ]; then
    echo ""
    echo "  A patch target matched by a .gitignore rule cannot be re-added once"
    echo "  removed — 'git add -A' skips it silently. Negate the rule (see the"
    echo "  !/composer.lock precedent in app/.gitignore) rather than relying on"
    echo "  'git add -f', which only works for whoever remembers to use it."
    exit 1
fi

echo "  $checked patch targets and sources are trackable ($exempt exempt under app/vendor/)"
exit 0
