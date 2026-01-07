<?php

namespace MediaWiki\Extension\NSFileRepo;

class Setup {

	/**
	 * @return void
	 */
	public static function register() {
		$GLOBALS['wgLocalFileRepo']['class'] = NamespaceLocalRepo::class;
		if ( defined( 'MW_QUIBBLE_CI' ) ) {
			return;
		}
		// Remove the default illegal char ':' - needed it to determine NS
		$GLOBALS['wgIllegalFileChars'] = str_replace( ":", "", $GLOBALS['wgIllegalFileChars'] );
	}
}
