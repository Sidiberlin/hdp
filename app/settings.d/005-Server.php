<?php
/**
 * Make $wgServer follow the MW_SERVER environment variable.
 *
 * maintenance/install.php bakes --server into LocalSettings.php once, at
 * first boot. After that the wiki's idea of its own URL is frozen: moving
 * the deployment behind a real domain meant hand-editing a generated file,
 * even though .env.example already advertises
 *
 *     MW_SERVER=https://wiki.example.com
 *
 * as a supported setting. Re-reading the variable here makes that promise
 * true for existing installs too — change MW_SERVER, restart, done.
 *
 * Loads at 005 so it lands before anything that derives a URL from
 * $wgServer, e.g. $wgCollabPadsBackendServiceURL in
 * 040-BlueSpiceProDistribution.php.
 *
 * No-op when MW_SERVER is unset: LocalSettings.php keeps whatever
 * install.php wrote. (docker/wiki/www.conf sets clear_env = no, which is
 * what lets container environment variables reach PHP-FPM workers.)
 */

$hdpServer = getenv( 'MW_SERVER' );
if ( is_string( $hdpServer ) && $hdpServer !== '' ) {
	$hdpServer = rtrim( $hdpServer, '/' );
	$GLOBALS['wgServer'] = $hdpServer;
	// Keep the canonical URL in step, otherwise e-mail notifications and
	// job-queue-generated links keep pointing at the install-time host.
	$GLOBALS['wgCanonicalServer'] = $hdpServer;
}
unset( $hdpServer );
