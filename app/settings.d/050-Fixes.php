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

// DevelopmentSettings.php also sets $wgShowExceptionDetails = true
// (line 54) and $wgShowHostnames = true (line 55). Nothing else resets
// either, so every uncaught exception — including on anonymous-reachable
// REST routes — was answered with the full exception class, message and
// backtrace in the response body, and DBConnectionError outage pages
// (MWExceptionRenderer::reportOutageHTML) additionally embedded the raw
// DB error message including the DB host/port. Return only the generic
// error message instead — core's generic renderer still names the
// exception class and request ID — and stop exposing hostnames. CLI
// maintenance scripts force $wgShowExceptionDetails back to true
// (Maintenance.php finalSetup), so installer and maintenance error
// paths keep their verbose output.
$GLOBALS['wgShowExceptionDetails'] = false;
$GLOBALS['wgShowHostnames'] = false;

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

// Re-establish the 'error' and 'exception' log channels as this file's
// last statement, after the debug-log wipe near the top.
//
// That wipe is needed (DevelopmentSettings.php fills cache/ with
// unrotated mw-debug-*.log files), but it also clears the
// $wgDebugLogGroups sinks DevelopmentSettings installs under MW_LOG_DIR.
// settings.d/010-Logging.php would normally keep exactly these two
// channels alive, but its fallback only fires when it loads with
// $wgDebugLogFile/$wgDebugLogGroups still empty — and it loads BEFORE
// this file, while DevelopmentSettings' MW_LOG_DIR logging is still
// active, so it returns early here and installs nothing. With no
// channel configured, the default LegacySpi then drops every event
// (LegacyLogger::shouldEmit() discards unconfigured channels when
// $wgDebugLogFile is empty): a web exception would produce the generic
// 500 body configured above and leave no diagnostic artifact anywhere.
// (DB errors are unaffected — they keep their own sink via the
// untouched $wgDBerrorLog, cache/mw-dberror.log.)
//
// Mirror 010-Logging.php's sink for these two channels: an
// ERROR-threshold handler (its Monolog config sets level 400/ERROR for
// the error and exception groups), writing to DevelopmentSettings' own
// cache/mw-error.log file. Exceptions — including their redacted
// backtrace, $wgLogExceptionBacktrace defaults to true — then land in
// the private cache/ logs while anonymous response bodies stay generic.
// Only ERROR+ events are written, so the file stays small without
// rotation.
$GLOBALS['wgDebugLogGroups'] = [
	'error' => [
		'destination' => "$IP/cache/mw-error.log",
		'level' => 'error', /* \Psr\Log\LogLevel::ERROR */
	],
	'exception' => [
		'destination' => "$IP/cache/mw-error.log",
		'level' => 'error', /* \Psr\Log\LogLevel::ERROR */
	],
];
