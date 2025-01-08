<?php

/**
 * Some default configuration that is needed for this extension
 */

if ( defined( 'MW_PHPUNIT_TEST' ) ) {
	return;
}

// Remove the default illegal char ':' - needed it to determine NS
$GLOBALS['wgIllegalFileChars'] = str_replace( ":", "", $GLOBALS['wgIllegalFileChars'] );

//Activate "nsfr_img_auth.php"
//This may be obsolete in future MW versions
$GLOBALS['wgUploadPath'] = $GLOBALS['wgScriptPath'] .'/nsfr_img_auth.php';
