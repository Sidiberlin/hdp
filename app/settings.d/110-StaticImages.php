<?php
/**
 * Allow embedding the architecture diagram (and any future static diagrams)
 * from this server's own static skins path.
 *
 * Background: BlueSpice routes all wiki-uploaded files through
 * nsfr_img_auth.php, which blocks image rendering with 403 in this
 * Docker deployment. Serving PNGs from skins/ (static Apache path)
 * bypasses the auth gate entirely and works on all browsers.
 *
 * The diagram image lives at app/skins/hdp/architecture.png and is
 * referenced from Help:Architektur.
 */

// Must stay false. Parser::maybeMakeExternalImage() short-circuits on
// getAllowExternalImages() BEFORE it ever consults the allow list
// (includes/parser/Parser.php:2415), so setting this to true silently
// disables $wgAllowExternalImagesFrom below and hot-links images from any
// host on the internet. That would let any editor turn a wiki page into a
// tracking beacon: every reader's IP, user agent and referrer would be sent
// to a third-party server on page view — a DSGVO problem in a public-sector
// wiki, not just a hardening nit.
$GLOBALS['wgAllowExternalImages'] = false;
// Allow both localhost and LAN IP access patterns.
// MW_SERVER is set in docker-compose.yml environment.
// Prefix match (strpos === 0), so these cover /w/skins/hdp/architecture.png.
$GLOBALS['wgAllowExternalImagesFrom'] = array_values( array_filter( [
    'http://localhost',
    rtrim($GLOBALS['wgServer'] ?? '', '/'),
] ) );
