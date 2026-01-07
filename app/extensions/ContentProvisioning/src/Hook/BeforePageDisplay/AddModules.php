<?php

namespace MediaWiki\Extension\ContentProvisioning\Hook\BeforePageDisplay;

use MediaWiki\Output\Hook\BeforePageDisplayHook;

class AddModules implements BeforePageDisplayHook {

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModules( 'ext.contentProvisioning.bootstrap' );
	}
}
