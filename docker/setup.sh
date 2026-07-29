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
max_wait=60
waited=0
while ! mariadb -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" -e "SELECT 1" "${DB_NAME}" &>/dev/null; do
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
# Two packages (hallowelt/chatbot, mediawiki/page-header) point to
# gitlab.hallowelt.com (private, no auth). Their full source is already
# committed in extensions/ChatBot/ and extensions/PageHeader/.
# We: disable the merge-plugin (composer.local.json → {}),
#     strip the two packages from composer.lock,
#     rm vendor/, reinstall, restore, dump-autoload.

if [ ! -f vendor/autoload.php ] || [ ! -f vendor/autoload_real.php ]; then
    echo ""
    echo "[1/4] Resolving Composer dependencies..."

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
    # extensions/*/composer.json via the merge-plugin
    composer dump-autoload --no-dev --ignore-platform-reqs

    echo "[1/4] Composer dependencies resolved."
else
    echo "[1/4] Vendor directory already present, skipping composer."
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
    php maintenance/install.php \
        --dbtype mysql \
        --dbserver "${DB_HOST}" \
        --dbuser "${DB_USER}" \
        --dbpass "${DB_PASS}" \
        --dbname "${DB_NAME}" \
        --installdbuser "${DB_USER}" \
        --installdbpass "${DB_PASS}" \
        --server "${SERVER}" \
        --scriptpath /w \
        --lang "${LANG}" \
        --pass "${ADMIN_PASS}" \
        "${SITENAME}" Admin

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

# ─── Step 3: Run update.php (creates extension tables) ────────────
echo ""
echo "[3/4] Running update.php (creates BlueSpice extension tables)..."
php maintenance/run.php update.php --quick --skip-config-validation
echo "[3/4] update.php complete."

# ─── Step 4: Fix permissions and runtime stubs ────────────────────
echo ""
echo "[4/4] Fixing permissions and runtime stubs..."

# Create data directories that extensions expect but don't ship empty
mkdir -p extensions/BlueSpiceFoundation/data extensions/SemanticMediaWiki/data 2>/dev/null || true

# The Wikimedia dev image's ResourceLoader expects tests/qunit/QUnitTestResources.php
# (normally generated by Wikimedia dev tooling, not part of the HDP repo).
# Without it, every page load fatals with "Unsupported operand types: bool + array".
mkdir -p tests/qunit
if [ ! -f tests/qunit/QUnitTestResources.php ]; then
    echo '<?php return [];' > tests/qunit/QUnitTestResources.php
fi

# Fix ownership for FPM workers (www-data)
chown -R www-data:www-data cache/ images/ tests/ \
    extensions/BlueSpiceFoundation/data extensions/SemanticMediaWiki/data 2>/dev/null || true

# Re-run update.php now that data directories exist (first run may have
# skipped SMW setup due to missing writable data dir)
echo "  Re-running update.php with data dirs in place..."
php maintenance/run.php update.php --quick --skip-config-validation 2>&1 | tail -5

echo "[4/4] Done."

echo ""
echo "============================================"
echo " ✓ Setup complete!"
echo "============================================"
echo ""
echo " Wiki:    ${SERVER}/w/"
echo " Admin:   ${SERVER}/w/index.php/Special:UserLogin"
echo " User:    Admin"
echo ""
echo "============================================"
