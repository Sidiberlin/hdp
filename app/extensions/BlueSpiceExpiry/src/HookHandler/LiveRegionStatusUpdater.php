<?php

namespace BlueSpice\Expiry\Hookhandler;

use BlueSpice\Expiry\Extension;
use MediaWiki\Hook\BeforePageDisplayHook;

class LiveRegionStatusUpdater implements BeforePageDisplayHook {

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$title = $out->getTitle();
		if ( !$title ) {
			return;
		}

		$expiry = Extension::getExpiryForPage( $title->getArticleID() );
		if ( !$expiry ) {
			return;
		}

		$out->addModules( 'ext.bluespice.expiry.liveRegionStatusUpdater' );
	}
}
