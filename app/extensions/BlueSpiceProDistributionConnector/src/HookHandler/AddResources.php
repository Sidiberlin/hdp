<?php

namespace BlueSpice\ProDistributionConnector\HookHandler;

use MediaWiki\Output\OutputPage;

class AddResources {

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModules( "ext.prodistributionconnector.droplet.modalDialog.execution.script" );
	}
}
