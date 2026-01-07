<?php

/**
 * THIS SCRIPT IS A COPY OF MEDIAWIKI CORE img_auth.php ENTRY POINT
 * altered to allow for namespace detection in image paths
 */

use MediaWiki\Context\RequestContext;
use MediaWiki\EntryPointEnvironment;
use MediaWiki\Extension\NSFileRepo\NamespaceAwareFileEntryPoint;
use MediaWiki\Extension\NSFileRepo\NSFileRepoHelper;
use MediaWiki\MediaWikiServices;

define( 'MW_NO_OUTPUT_COMPRESSION', 1 );
define( 'MW_ENTRY_POINT', 'img_auth' );
require __DIR__ . '/includes/WebStart.php';

( new NamespaceAwareFileEntryPoint(
	RequestContext::getMain(),
	new EntryPointEnvironment(),
	MediaWikiServices::getInstance(),
	new NSFileRepoHelper(),
	$GLOBALS['egNSFileRepoForceDownload'] ?? []
) )->run();
