<?php

if( !defined( 'WIKI_FARMING' ) ) return;

wfLoadExtension( 'BlueSpiceInterwikiSearch' );
$GLOBALS['wgExtensionFunctions'][] = 'BlueSpice\\WikiFarm\\Setup::setupSearchInOtherWikisConfig';

wfLoadExtension( 'BlueSpiceTranslationTransfer' );
$GLOBALS['wgExtensionFunctions'][] = 'BlueSpice\\WikiFarm\\Setup::setupTranslationLinks';

// ERM41612: Temporarily adjust settings to avoid bug new installations.
// Can be removed once bug is properly fixed.
$GLOBALS['bsgDeeplTranslateConversionConfig'] = [
	'translatePageTitle' => true,
	'translateNamespaces' => true
];