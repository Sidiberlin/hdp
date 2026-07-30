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

$GLOBALS['wgAllowExternalImages'] = true;
// Allow both localhost and LAN IP access patterns.
// MW_SERVER is set in docker-compose.yml environment.
$GLOBALS['wgAllowExternalImagesFrom'] = [
    'http://localhost',
    rtrim($GLOBALS['wgServer'] ?? '', '/'),
];
