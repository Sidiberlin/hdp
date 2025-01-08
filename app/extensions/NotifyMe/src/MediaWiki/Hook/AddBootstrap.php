<?php

namespace MediaWiki\Extension\NotifyMe\MediaWiki\Hook;

use MediaWiki\Hook\BeforePageDisplayHook;

class AddBootstrap implements BeforePageDisplayHook {
	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModules( [ 'ext.notifyme.bootstrap' ] );
	}
}
