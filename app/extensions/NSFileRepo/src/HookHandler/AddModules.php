<?php
namespace MediaWiki\Extension\NSFileRepo\HookHandler;

use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\Specials\SpecialUpload;

class AddModules implements SpecialPageBeforeExecuteHook {

	/**
	 * @inheritDoc
	 */
	public function onSpecialPageBeforeExecute( $special, $subpage ) {
		$script = file_get_contents( __DIR__ . '/../../resources/ext.nsfilerepo.special.upload.js' );
		if ( $special && $special instanceof SpecialUpload ) {
			$special->getOutput()->addInlineScript( $script );
		}
		if ( $special && class_exists( '\PFUploadWindow' ) && $special instanceof \PFUploadWindow ) {
			// temporarily allow iframe in x-frame-options
			$GLOBALS['wgBreakFrames'] = false;
			$special->getOutput()->prependHTML( "<script>$script</script>" );
		}
		return true;
	}
}
