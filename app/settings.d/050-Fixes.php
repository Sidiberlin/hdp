<?php

// Slightly increase session expiry to avoid logout issues caused by timezone misconfiguration
$GLOBALS['wgObjectCacheSessionExpiry'] = 3 * 60 * 60;

// Avoid `MWUnknownContentModelException` on legacy databases
$GLOBALS['wgContentHandlers']['BSSocial'] = 'FallbackContentHandler';
$GLOBALS['wgContentHandlers']['BSSocialDiscussion'] = 'FallbackContentHandler';
$GLOBALS['wgContentHandlers']['BSSocialProfile'] = 'FallbackContentHandler';

// The Wikimedia dev image's PlatformSettings.php (required early in
// LocalSettings.php, before this settings.d/ loader) sets
// $wgSQLMode = 'STRICT_ALL_TABLES,ONLY_FULL_GROUP_BY' via DevelopmentSettings.php
// (T108255). ONLY_FULL_GROUP_BY breaks several BlueSpice extensions that run
// non-standard GROUP BY queries — e.g. BlueSpiceUserSidebar's "recently
// visited pages" widget fails with "Error 1055: ... isn't in GROUP BY" on
// every single logged-in page load (including the main page). Since this
// global is applied by MediaWiki's PHP DB layer on every connection, fixing
// only the MariaDB server-side sql_mode (see docker/mariadb/sql-mode.cnf) is
// not sufficient — $wgSQLMode always wins. Drop ONLY_FULL_GROUP_BY here,
// after PlatformSettings.php has already run.
$GLOBALS['wgSQLMode'] = 'STRICT_ALL_TABLES';

// The Wikimedia dev image's DevelopmentSettings.php (loaded via
// PlatformSettings.php) enables verbose debug logging by default —
// writing to cache/mw-debug-cli.log and cache/mw-debug-web.log with
// no rotation. On a fresh install this produced 150 MB of CLI debug
// logs within minutes, and 2 GB on a longer-lived instance.
// Disable it for production use; set $wgDebugLogFile manually if
// debugging is needed.
$GLOBALS['wgDebugLogFile'] = '';
$GLOBALS['wgDebugLogGroups'] = [];
$GLOBALS['wgDebugToolbar'] = false;

// BlueSpiceExtendedSearch's default backend config (extension.json)
// points at 127.0.0.1:9200, which is nothing inside the mediawiki
// container — OpenSearch is a separate service reachable at
// opensearch:9200. Without this override, every search query throws
// OpenSearch\Common\Exceptions\NoNodesAvailableException and the
// Search Center UI never renders results.
//
// BlueSpice\Config (registered as the 'bsg' config factory by
// BlueSpiceFoundation) is a MultiConfig chain that checks a
// database-backed settings table BEFORE plain $wgBsg* globals, so a
// normal $wgBsgESBackendHost override here is silently shadowed by
// the DB-seeded default. The one layer that wins over everything —
// including the DB config — is GlobalVarConfig('bsgOverride'), read
// directly as $GLOBALS['bsgOverride<Key>'] (no "wg" prefix).
$GLOBALS['bsgOverrideESBackendHost'] = 'opensearch';
$GLOBALS['bsgOverrideESBackendPort'] = '9200';
$GLOBALS['bsgOverrideESBackendTransport'] = 'https';
$GLOBALS['bsgOverrideESBackendUsername'] = 'admin';
$GLOBALS['bsgOverrideESBackendPassword'] = getenv( 'HDP_OPENSEARCH_PASSWORD' ) ?: '';
