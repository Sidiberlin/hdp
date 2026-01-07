<?php

namespace MediaWiki\Extension\TableTools\HookHandler;

use MediaWiki\Output\Hook\OutputPageBeforeHTMLHook;

class StickyHeader implements OutputPageBeforeHTMLHook {

	/**
	 * @inheritDoc
	 */
	public function onOutputPageBeforeHTML( $out, &$text ) {
		$regex = '/<table[^>]*class=[\'"][^\'"]*mw-sticky-header[^\'"]*[\'"][^>]*>.*?<\/table>/si';
		if ( preg_match( $regex, $text ) ) {
			$out->addModules( 'ext.tableTools.stickyHeader' );
		}
	}
}
