#!/bin/bash
# ============================================================
# BlueSpice HDP — First-boot Setup Script
# Runs INSIDE the mediawiki (PHP-FPM) container.
#
# Does three things:
#   1. Resolves the Composer problem (two private hallowelt packages
#      whose full source is already committed in extensions/)
#   2. Installs MediaWiki with MariaDB backend
#   3. Runs update.php to create all extension tables
#
# Usage:
#   docker compose exec mediawiki bash /setup.sh
# ============================================================
set -euo pipefail

MW=/var/www/html/w
cd "$MW"

# ─── Failure tracking ───────────────────────────────────────────────
# This script runs a chain of steps that can each fail without failing the
# script. `composer dump-autoload` fires composer.local.json's
# pre-autoload-dump hook, which runs the eight scripts in
# _bluespice/pre-autoload-dump.d/ through a loop that ignores every exit
# status; 99-apply_patches.sh in particular prints "FAILED!" per patch and
# carries on. None of that reaches this script's exit code.
#
# The Class-A re-application below used to be two `sed -i` blocks with the same
# problem — sed exits 0 when it matches nothing. Those are now
# scripts/apply-patches.sh, which reports per-patch failures this tracker
# collects; the tracking stays because the pre-autoload-dump chain is still
# silent and is not ours to fix.
#
# The result, verified on a clean box: three separate failures — one dropped
# patch, a destroyed-and-not-restored mw-config/overrides, and two tool
# downloads — while setup printed "✓ Setup complete!" and exited 0.
#
# Policy here is deliberately warn-then-fail-at-end, NOT abort-on-first-error.
# Under `set -e` an abort would leave a half-installed wiki, which is strictly
# worse for the operator than a finished install plus an accurate report — and
# an upstream reindent that breaks one patch anchor should not cost everyone
# their wiki. So: record, keep going, summarise, exit non-zero.
#
# Two severities, because a gate that cries wolf gets ignored:
#   fail — the wiki is wrong (a patch is missing, a required tree is gone)
#   warn — degraded but nothing downstream depends on it
HDP_FAILURES=()
HDP_WARNINGS=()

record_failure() {
    HDP_FAILURES+=( "$1" )
    printf '  \033[0;31m✗ FAILED\033[0m  %s\n' "$1" >&2
}

record_warning() {
    HDP_WARNINGS+=( "$1" )
    printf '  \033[0;33m! WARNING\033[0m %s\n' "$1" >&2
}

# Scan the captured pre-autoload-dump output for the failures its own scripts
# report but do not propagate. Log-scraping is the only option for the patch
# loop — it emits no machine-readable signal and no exit code — so the marker
# strings are matched after stripping the ANSI colour codes it wraps them in.
scan_pre_autoload_dump() {
    local log="$1" plain line
    plain="$(mktemp)"
    sed -e 's/\x1b\[[0-9;]*m//g' "$log" > "$plain"

    # 99-apply_patches.sh: "Patching: <target> ==> FAILED!"
    #
    # A failed patch is graded by whether its target still exists, because the
    # two cases need opposite responses:
    #
    #   target present -> the anchor moved under us. The file is live and now
    #                     unpatched. Real failure.
    #   target absent  -> upstream deleted the file the patch was written
    #                     against, so the patch can never apply again and
    #                     there is nothing to fix here. Reporting it as a
    #                     failure would make setup.sh exit non-zero on every
    #                     run forever, which trains everyone to ignore the
    #                     exit code — see docs/dev/patches.md.
    #
    # This is a state check rather than a hardcoded stale-list so it stays
    # correct without maintenance. It does conflate "upstream removed the
    # file" with "the extension is not installed at all"; the latter would
    # normally show up as many failures at once, not one.
    while IFS= read -r line; do
        [ -n "$line" ] || continue
        if [ -e "$MW/$line" ]; then
            record_failure "patch did not apply, target still present: $line"
        else
            record_warning "stale patch skipped, target no longer exists upstream: $line"
        fi
    done < <(grep -oE 'Patching: [^ ]+ ==> FAILED!' "$plain" \
             | sed -E 's/^Patching: (.*) ==> FAILED!$/\1/' || true)

    # 10-add_tools.sh (this repo's pinned version) reports its own errors.
    while IFS= read -r line; do
        [ -n "$line" ] || continue
        record_warning "optional tool not installed: $line"
    done < <(grep -oE '10-add_tools\.sh: ERROR: .*' "$plain" || true)

    rm -f "$plain"
}

# ─── Load secrets from Infisical ────────────────────────────────────
if [ -f /infisical-loader.sh ]; then
    source /infisical-loader.sh
fi

# ─── Environment ───────────────────────────────────────────────────
DB_HOST="${DB_HOST:-mariadb}"
DB_NAME="${MW_DB_NAME:-bluespice}"
DB_USER="${MW_DB_USER:-bluespice}"
DB_PASS="${MW_DB_PASS:-${HDP_DB_PASSWORD}}"
ADMIN_PASS="${WIKI_ADMIN_PASS:-${HDP_ADMIN_PASSWORD}}"
SERVER="${MW_SERVER:-http://localhost:8080}"
LANG="${MW_LANG:-de}"
SITENAME="${MW_SITENAME:-BlueSpice HDP}"

echo "============================================"
echo " BlueSpice HDP Setup"
echo "============================================"
echo " DB:   ${DB_USER}@${DB_HOST}/${DB_NAME}"
echo " URL:  ${SERVER}/w"
echo " Lang: ${LANG}"
echo "============================================"
echo ""

# ─── Step 0: Wait for MariaDB ──────────────────────────────────────
echo "[0/4] Waiting for MariaDB at ${DB_HOST}..."
max_wait=120
waited=0
# MYSQL_PWD rather than -p"${DB_PASS}", for the same reason install.php below
# gets its passwords from files: an argv password is world-readable via
# /proc/<pid>/cmdline, and this loop can spawn up to 60 clients. The
# environment is not perfect either, but /proc/<pid>/environ is readable only
# by the process owner, and the variable is scoped to the one command.
while ! MYSQL_PWD="${DB_PASS}" mariadb -h "${DB_HOST}" -u "${DB_USER}" -e "SELECT 1" "${DB_NAME}" &>/dev/null; do
    if [ "$waited" -ge "$max_wait" ]; then
        echo "ERROR: MariaDB not reachable after ${max_wait}s"
        exit 1
    fi
    sleep 2
    waited=$((waited + 2))
    printf "."
done
echo " OK (${waited}s)"

# ─── Step 1: Fix Composer ─────────────────────────────────────────
# ─── Pre-step: Install python3 for SyntaxHighlight (Pygments) ──────
# The Wikimedia dev image doesn't include python3, but BlueSpice's
# SyntaxHighlight_GeSHi extension shells out to Pygments for code
# block rendering. Without it, every page with a code block spams
# PHP notices and renders as plain <pre>.
if ! command -v python3 &>/dev/null; then
    echo "Installing python3 + pygments for syntax highlighting..."
    # mirrors.wikimedia.org is unresolvable outside Wikimedia's own network —
    # the base image's default Debian mirror. Point at deb.debian.org instead
    # so apt-get update can actually populate a package index.
    sed -i 's#mirrors\.wikimedia\.org#deb.debian.org#g' /etc/apt/sources.list
    # The base image ships /etc/apt/sources.list.d/php.list pointing at
    # packages.sury.org, whose signing key has expired:
    #   E: The repository 'https://packages.sury.org/php bookworm InRelease'
    #      is not signed.  (EXPKEYSIG B188E2B695BD4743)
    # apt-get update then exits non-zero, which the `|| true` below swallows,
    # so the whole run looks like it failed even when the index is fine. We
    # install nothing from sury — python3 and python3-pygments come from Debian
    # main — so drop the source rather than chase a fresh key.
    rm -f /etc/apt/sources.list.d/php.list /etc/apt/sources.list.d/sury*.list
    apt-get update -qq || true
    apt-get install -y -qq --no-install-recommends python3 python3-pygments >/dev/null 2>&1
    echo "  Done."
fi

# Two packages (hallowelt/chatbot, mediawiki/page-header) point to
# gitlab.hallowelt.com (private, no auth). Their full source is already
# committed in extensions/ChatBot/ and extensions/PageHeader/.
# We: disable the merge-plugin (composer.local.json → {}),
#     strip the two packages from composer.lock,
#     rm vendor/, reinstall, restore, dump-autoload.

if [ ! -f vendor/autoload.php ] || [ ! -f vendor/composer/autoload_real.php ]; then
    echo ""
    echo "[1/4] Resolving Composer dependencies..."

    # Force HTTPS-only GitHub access. A composer.lock that pins a package's
    # VCS source to the SSH form (git@github.com:...) breaks fresh containers,
    # which have no SSH client or keys: composer fails with either "cannot run
    # ssh: No such file or directory" or an internal TypeError in
    # Git::runCommand(). The github-protocols config alone does not help — it
    # does not rewrite an already-pinned SSH URL.
    #
    # The committed composer.lock now stores https:// sources, so this sed is
    # a no-op on a clean checkout. It stays as a safety net for locks
    # regenerated by a developer whose git rewrites github.com to SSH.
    sed -i 's#git@github\.com:#https://github.com/#g' composer.lock
    composer config --global github-protocols https

    # Backup and neutralize composer.local.json (disables MWStake merge-plugin
    # which declares the VCS repos for the private packages)
    if [ -f composer.local.json ]; then
        cp composer.local.json composer.local.json.bak
    fi
    echo '{}' > composer.local.json

    # Strip the two private packages from composer.lock using jq
    if command -v jq &>/dev/null; then
        jq 'del(.packages[] | select(.name == "hallowelt/chatbot" or .name == "mediawiki/page-header"))' \
            composer.lock > composer.lock.tmp
        mv composer.lock.tmp composer.lock
    else
        # Fallback: python3
        python3 -c "
import json, sys
with open('composer.lock') as f:
    lock = json.load(f)
lock['packages'] = [p for p in lock.get('packages', [])
                    if p.get('name') not in ('hallowelt/chatbot', 'mediawiki/page-header')]
with open('composer.lock', 'w') as f:
    json.dump(lock, f, indent=4)
"
    fi

    # Remove vendor/ and reinstall (ignores the stripped packages)
    rm -rf vendor/
    git config --global --add safe.directory "${MW}" || true
    git config --global --add safe.directory '*' || true
    composer install --no-dev --no-interaction --prefer-dist --ignore-platform-reqs

    # Restore composer.local.json
    if [ -f composer.local.json.bak ]; then
        mv composer.local.json.bak composer.local.json
    fi

    # Regenerate autoloader — picks up extension namespaces from
    # extensions/*/composer.json via the merge-plugin.
    #
    # This is also what fires the pre-autoload-dump hook, i.e. all eight
    # scripts in _bluespice/pre-autoload-dump.d/, including the one that
    # applies 18 .diff patches. Capture the output so their failures can be
    # detected; `tee` keeps it on the console exactly as before.
    #
    # 05-add_installer_overrides.sh is checked by state rather than by log
    # scraping: it `rm -rf`s mw-config/overrides/ and re-clones it, so the
    # honest question afterwards is simply whether the directory came back.
    OVERRIDES_BEFORE=0
    [ -d mw-config/overrides ] && [ -n "$(ls -A mw-config/overrides 2>/dev/null)" ] && OVERRIDES_BEFORE=1

    DUMP_LOG="$(mktemp)"
    composer dump-autoload --no-dev --ignore-platform-reqs 2>&1 | tee "$DUMP_LOG"
    scan_pre_autoload_dump "$DUMP_LOG"
    rm -f "$DUMP_LOG"

    if [ "$OVERRIDES_BEFORE" -eq 1 ] \
       && { [ ! -d mw-config/overrides ] || [ -z "$(ls -A mw-config/overrides 2>/dev/null)" ]; }; then
        record_failure "mw-config/overrides was deleted and not restored (05-add_installer_overrides.sh re-clone failed)"
    fi

    echo "[1/4] Composer dependencies resolved."
else
    echo "[1/4] Vendor directory already present, skipping composer."
fi

# ─── Post-composer: re-apply the patches composer clobbered ─────────
# `composer install` reinstalls bluespice/extendedsearch as a dist zipball,
# overwriting the two files this project patches. They have to go back on
# after every install.
#
# This used to be two inline `sed -i` blocks. They are gone, for three reasons:
#
#   1. `sed -i` exits 0 when its address matches nothing, so an upstream
#      reindent turned the patch into a silent no-op — the failure mode the
#      whole patch strategy exists to kill.
#   2. The patch text lived only here, so nothing else could verify it. The
#      manifest in docker/patches/ is now the single description, shared by
#      the applier and the verifier.
#   3. `patch --fuzz 3` survives the whitespace and small context drift that
#      broke the exact-string sed match, and it is the same tool (and the same
#      fuzz factor) that 99-apply_patches.sh already uses for the inherited
#      BlueSpice patches.
#
# Class A only: the 18 inherited Class-C patches are applied by
# 99-apply_patches.sh during `composer dump-autoload`, above.
#
# Paths are passed explicitly because the container has no repo root — only
# these three mounts exist (see docker-compose.yml).
if [ -x /hdp-scripts/apply-patches.sh ] && [ -d /hdp-patches ]; then
    echo ""
    echo "[1/4] Re-applying composer-clobbered patches..."
    APPLY_LOG="$(mktemp)"
    if HDP_PATCH_MANIFEST_DIR=/hdp-patches \
       HDP_APP_DIR="$MW" \
       HDP_PATCH_LIB_DIR=/hdp-scripts/lib \
       NO_COLOR=1 \
       bash /hdp-scripts/apply-patches.sh --class A 2>&1 | tee "$APPLY_LOG"; then
        :
    else
        # Warn and continue; the summary at the end carries the exit code. An
        # abort here under `set -e` would leave a half-installed wiki.
        #
        # Record one failure per named patch when the applier reported them,
        # and a single generic failure otherwise — exit 2 (missing python3 or a
        # malformed manifest) produces no per-patch lines, and swallowing that
        # would be the silent no-op this whole change is meant to remove.
        _apply_named=0
        while IFS= read -r line; do
            [ -n "$line" ] || continue
            record_failure "patch not re-applied: $line"
            _apply_named=1
        # apply-patches.sh emits one `HDP_PATCH_FAILED=<id>` line per failure,
        # at column 0, pure ASCII. This used to read the human line
        # (`  ✗ <id> — <title>`) with `grep -oE '^  . [a-z0-9-]+ —'`, which
        # breaks in a C/POSIX locale — the default in this image, since nothing
        # sets one — because `✗` is three UTF-8 bytes and ERE `.` matches one.
        # The result was not a silent pass (setup.sh still exits 1) but every
        # named failure degraded to the generic message below, dropping exactly
        # the patch ids an operator needs. Contract now, not formatting.
        done < <(sed -n 's/^HDP_PATCH_FAILED=\([a-z0-9-]*\)$/\1/p' "$APPLY_LOG" 2>/dev/null || true)
        if [ "$_apply_named" -eq 0 ]; then
            record_failure "apply-patches.sh failed before it could report per-patch results (see output above)"
        fi
    fi
    rm -f "$APPLY_LOG"
else
    record_warning "apply-patches.sh or the patch manifest is not mounted; Class-A patches were NOT re-applied"
fi

# ─── Step 2: Install MediaWiki (MariaDB) ──────────────────────────
echo ""
if [ -f LocalSettings.php ]; then
    echo "[2/4] LocalSettings.php exists — skipping install."
else
    echo "[2/4] Installing MediaWiki with MariaDB..."

    # install.php is a standalone script — call it directly, NOT via run.php
    # (run.php prepends maintenance/ and would look for maintenance/maintenance/install.php)
    # NOTE: Do NOT use --with-extensions: it auto-discovers Echo and writes
    # wfLoadExtension('Echo') into LocalSettings.php, which then conflicts with
    # the BlueSpice shim in settings.d/030-BlueSpiceFreeDistribution.php that
    # loads Echo from a different path ("loaded twice" fatal).
    #
    # The two passwords go in through --dbpassfile/--passfile, not
    # --dbpass/--pass. Anything in argv is world-readable via
    # /proc/<pid>/cmdline for the whole life of the process, and install.php
    # is not a quick one — it builds the entire schema. Same reasoning as the
    # Infisical login body and bearer token in docker/infisical-loader.sh.
    # The files are written under a 0700 dir with umask 077 and removed on the
    # way out, including on failure, via the trap.
    #
    # --installdbuser/--installdbpass are dropped rather than converted,
    # because install.php has no --installdbpassfile. That is behaviour-
    # identical here, not a shortcut: with --installdbuser absent, CliInstaller
    # defaults _InstallUser/_InstallPassword to wgDBuser/wgDBpassword — exactly
    # the ${DB_USER}/${DB_PASS} pair they were being handed. The one other
    # thing --installdbuser did was flip _CreateDBAccount on, and
    # MysqlInstaller::setupUser() returns early whenever wgDBuser equals
    # _InstallUser, so that flag was never read in this configuration. The
    # account is created by MariaDB's own MYSQL_USER bootstrap regardless.
    PASSDIR="$(mktemp -d)"
    chmod 700 "$PASSDIR"
    trap 'rm -rf "$PASSDIR"' EXIT
    (
        umask 077
        # No trailing newline: install.php trims only "\r\n", so an
        # echo here would still be right, but printf keeps the file
        # byte-identical to the variable.
        printf '%s' "${DB_PASS}"    > "$PASSDIR/dbpass"
        printf '%s' "${ADMIN_PASS}" > "$PASSDIR/adminpass"
    )

    php maintenance/install.php \
        --dbtype mysql \
        --dbserver "${DB_HOST}" \
        --dbuser "${DB_USER}" \
        --dbpassfile "$PASSDIR/dbpass" \
        --dbname "${DB_NAME}" \
        --server "${SERVER}" \
        --scriptpath /w \
        --lang "${LANG}" \
        --passfile "$PASSDIR/adminpass" \
        "${SITENAME}" Admin

    rm -rf "$PASSDIR"
    trap - EXIT

    # The installer generates a clean LocalSettings.php that auto-detects
    # all extensions in extensions/ and writes wfLoadExtension() calls for
    # each one. But BlueSpice uses settings.d/*.php to load extensions in a
    # specific order with custom configuration. Having both causes "loaded
    # twice" fatals (e.g. Echo is loaded natively AND via a BlueSpice shim).
    # Solution: strip all auto-generated wfLoadExtension lines, then append
    # the BlueSpice settings loader.

    # Remove all auto-generated wfLoadExtension lines
    sed -i '/^wfLoadExtension(/d' LocalSettings.php

    # Append the BlueSpice settings loader (loads all settings.d/*.php —
    # this activates ~130 extensions in the correct order with config).
    if ! grep -q "LocalSettings.BlueSpice.php" LocalSettings.php; then
        echo "" >> LocalSettings.php
        echo "// BlueSpice extension settings" >> LocalSettings.php
        echo "require_once \"\$IP/LocalSettings.BlueSpice.php\";" >> LocalSettings.php
    fi

    # Suppress verbose deprecation notices in HTTP output
    # (Wikimedia dev image sets display_errors=1 via DevelopmentSettings.php)
    if ! grep -q "display_errors" LocalSettings.php; then
        echo "" >> LocalSettings.php
        echo "// Suppress dev-mode error display" >> LocalSettings.php
        echo "ini_set( 'display_errors', '0' );" >> LocalSettings.php
    fi

    echo "[2/4] MediaWiki installed."
fi

# ─── Pre-step 3: Prepare writable data dirs ───────────────────────
# SMW's setup writes .smw.json under BlueSpiceFoundation/data during
# update.php. If the directory isn't writable by the PHP user, update.php
# emits a scary "ERROR: .smw.json is not writable" line even though the
# overall run still succeeds. Preparing the dir + file (and fixing perms)
# BEFORE step [3/4] avoids the error entirely.
mkdir -p extensions/BlueSpiceFoundation/data extensions/SemanticMediaWiki/data 2>/dev/null || true
touch extensions/BlueSpiceFoundation/data/.smw.json 2>/dev/null || true

# The Wikimedia dev image's ResourceLoader expects tests/qunit/QUnitTestResources.php
# (normally generated by Wikimedia dev tooling, not part of the HDP repo).
# Without it, every page load fatals with "Unsupported operand types: bool + array".
mkdir -p tests/qunit
if [ ! -f tests/qunit/QUnitTestResources.php ]; then
    echo '<?php return [];' > tests/qunit/QUnitTestResources.php
fi

# Fix ownership for FPM workers (www-data) BEFORE update.php runs, so
# SMW's setup can write .smw.json on the first pass.
chown -R www-data:www-data cache/ images/ tests/ \
    extensions/BlueSpiceFoundation/data extensions/SemanticMediaWiki/data 2>/dev/null || true

# ─── Step 3: Run update.php (creates extension tables) ────────────
echo ""
echo "[3/4] Running update.php (creates BlueSpice extension tables)..."
php maintenance/run.php update.php --quick --skip-config-validation
echo "[3/4] update.php complete."

# ─── Step 4: Final permissions sweep ──────────────────────────────
echo ""
echo "[4/4] Final permissions sweep..."

# Re-apply ownership in case update.php created new files as root
chown -R www-data:www-data cache/ images/ tests/ \
    extensions/BlueSpiceFoundation/data extensions/SemanticMediaWiki/data 2>/dev/null || true

echo "[4/4] Done."

# ─── Step 4b: Populate main page (first install only) ─────────────
# Replace the generic upstream BlueSpice welcome boilerplate with usage
# instructions + an auto-updating content overview (DynamicPageList-driven
# "recently edited" list). Runs only once, guarded by a marker file, so
# later manual edits by wiki admins are never overwritten on container
# restart.
if [ -f /hauptseite.wiki ] && [ ! -f cache/.hauptseite-populated ]; then
    echo ""
    echo "[4/4] Populating main page with usage instructions..."
    php maintenance/run.php edit.php \
        --user Admin \
        --summary "Initial setup: populate main page with usage instructions" \
        Hauptseite < /hauptseite.wiki \
        && touch cache/.hauptseite-populated \
        || echo "  WARNING: main page population failed (non-fatal, continuing)"
fi

# ─── Step 4c: Populate Chatbot-FAQ (first install only) ───────────
# Ships a German FAQ page explaining how the chatbot works, what it
# can/can't answer, source attribution, and privacy. Guarded by a
# marker file so admin edits are never overwritten on restart.
if [ -f /chatbot-faq.wiki ] && [ ! -f cache/.chatbot-faq-populated ]; then
    echo ""
    echo "[4/4] Populating Chatbot-FAQ page..."
    php maintenance/run.php edit.php \
        --user Admin \
        --summary "Initial setup: populate Chatbot-FAQ page" \
        Chatbot-FAQ < /chatbot-faq.wiki \
        && touch cache/.chatbot-faq-populated \
        || echo "  WARNING: Chatbot-FAQ population failed (non-fatal, continuing)"
fi

# ─── Step 4c2: Populate Site: legal placeholder pages (first install) ─
# BlueSpice shows a yellow warning banner on every page until
# Site:Nutzungsbedingungen and Site:Datenschutz exist. These are
# minimal placeholders — the admin should customize them.
if [ ! -f cache/.site-pages-populated ]; then
    echo ""
    echo "[4/4] Populating Site: legal placeholder pages..."
    for page_file in \
        "Site:Nutzungsbedingungen|/site-nutzungsbedingungen.wiki" \
        "Site:Datenschutz|/site-datenschutz.wiki"; do
        page="${page_file%%|*}"
        file="${page_file##*|}"
        if [ -f "$file" ]; then
            echo "  -> ${page}"
            php maintenance/run.php edit.php \
                --user Admin \
                --summary "Initial setup: populate legal placeholder page" \
                "$page" < "$file" \
                || echo "  WARNING: population of ${page} failed (non-fatal, continuing)"
        fi
    done
    touch cache/.site-pages-populated
fi

# ─── Step 4c3: Populate QoL2 Site: legal placeholder pages (v2) ──────
# Site:Impressum / Site:Haftungsausschluss / Site:Über are linked from the
# BlueSpice footer (FooterLinks.DE.wiki) and were red links on fresh
# installs (Finding 2). Separate marker so existing installs gain exactly
# these pages and the two original pages are never re-overwritten.
if [ ! -f cache/.site-pages-populated-v2 ]; then
    echo ""
    echo "[4/4] Populating Site: legal placeholder pages (QoL2)..."
    missing=0
    for page_file in \
        "Site:Impressum|/site-impressum.wiki" \
        "Site:Haftungsausschluss|/site-haftungsausschluss.wiki" \
        "Site:Über|/site-ueber.wiki"; do
        page="${page_file%%|*}"
        file="${page_file##*|}"
        if [ -f "$file" ]; then
            echo "  -> ${page}"
            php maintenance/run.php edit.php \
                --user Admin \
                --summary "Initial setup: populate legal placeholder page" \
                "$page" < "$file" \
                || echo "  WARNING: population of ${page} failed (non-fatal, continuing)"
        else
            missing=1
        fi
    done
    if [ "$missing" -eq 0 ]; then
        touch cache/.site-pages-populated-v2
    else
        echo "  WARNING: one or more Site: page files are not mounted; not marking populated"
    fi
fi

# ─── Step 4d: Populate codewiki Help-namespace docs (first install only) ─
# Ships the docs/wiki/*.md tree (converted to wikitext by
# scripts/convert-docs.sh, checked into docker/mediawiki/wiki-docs/) into
# the Help namespace so the main-page links to Help:Technische_Dokumentation,
# Help:Architektur, Help:Erste_Schritte, Help:Modul/*, Help:Diagramme/*
# and Help:Inhaltsverzeichnis are no longer red. Uses '__' in filenames as
# a stand-in for '/' in subpage names (see convert-docs.sh).
# Guarded by a marker file so admin edits are never overwritten on restart.
if [ -d /wiki-docs ] && [ ! -f cache/.wiki-docs-populated ]; then
    echo ""
    echo "[4/4] Populating codewiki Help pages..."
    for f in /wiki-docs/*.wiki; do
        [ -f "$f" ] || continue
        base="$(basename "$f" .wiki)"
        # Turn 'Help:Modul__Ingestion' into 'Help:Modul/Ingestion'.
        page="${base//__//}"
        echo "  -> ${page}"
        php maintenance/run.php edit.php \
            --user Admin \
            --summary "Initial setup: populate codewiki-generated Help page" \
            "${page}" < "$f" \
            || echo "  WARNING: population of ${page} failed (non-fatal, continuing)"
    done
    touch cache/.wiki-docs-populated
fi

# ─── Step 4e: Initialize ExtendedSearch index (first install only) ──
# BlueSpice's ExtendedSearch needs the OpenSearch backend initialized and
# the initial index built. Without this, wiki search returns zero results.
# Guarded by a marker file so re-runs don't rebuild from scratch.
if [ ! -f cache/.extendedsearch-initialized ]; then
    echo ""
    echo "[4/4] Initializing ExtendedSearch index..."
    echo "  This queues background indexing jobs and may take a few minutes..."
    ES_MAINT="$MW/extensions/BlueSpiceExtendedSearch/maintenance"
    php maintenance/run.php "$ES_MAINT/initBackends.php" --quick 2>&1 \
        || echo "  WARNING: initBackends failed (may already be initialized)"
    php maintenance/run.php "$ES_MAINT/rebuildIndex.php" --quick 2>/dev/null \
        || echo "  WARNING: rebuildIndex failed (non-fatal, jobs may still be processing)"
    touch cache/.extendedsearch-initialized
    echo "  ExtendedSearch initialized. Background jobs will finish indexing."
fi


# ─── Summary ────────────────────────────────────────────────────────
# One line that always states the outcome, so "did this work?" never has to
# be answered by reading 700 lines of composer output.
echo ""
echo "============================================"
if [ ${#HDP_FAILURES[@]} -eq 0 ] && [ ${#HDP_WARNINGS[@]} -eq 0 ]; then
    echo " ✓ Setup complete — 0 failures, 0 warnings."
elif [ ${#HDP_FAILURES[@]} -eq 0 ]; then
    echo " ✓ Setup complete — 0 failures, ${#HDP_WARNINGS[@]} warning(s)."
else
    echo " ✗ Setup FINISHED WITH ERRORS — ${#HDP_FAILURES[@]} failure(s), ${#HDP_WARNINGS[@]} warning(s)."
fi
echo "============================================"

if [ ${#HDP_FAILURES[@]} -gt 0 ]; then
    echo ""
    echo " Failures (the wiki is installed but not correct):"
    for f in "${HDP_FAILURES[@]}"; do
        echo "   ✗ $f"
    done
fi

if [ ${#HDP_WARNINGS[@]} -gt 0 ]; then
    echo ""
    echo " Warnings (degraded, nothing downstream depends on these):"
    for w in "${HDP_WARNINGS[@]}"; do
        echo "   ! $w"
    done
fi

echo ""
echo " Wiki:    ${SERVER}/w/"
echo " Admin:   ${SERVER}/w/index.php/Special:UserLogin"
echo " User:    Admin"
echo ""
echo "============================================"

# Exit non-zero if anything failed. Deliberately at the very end, after the
# wiki is fully installed and usable — see the failure-tracking note at the
# top of this file for why aborting mid-run would be worse.
if [ ${#HDP_FAILURES[@]} -gt 0 ]; then
    echo ""
    echo "setup.sh: exiting 1 — ${#HDP_FAILURES[@]} step(s) failed. The wiki is" >&2
    echo "installed and reachable, but the failures above must be resolved." >&2
    exit 1
fi
