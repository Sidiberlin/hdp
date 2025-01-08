<?php

namespace MediaWiki\Extension\PageCheckout\HookHandler;

use BlueSpice\Discovery\Hook\BlueSpiceDiscoveryTemplateDataProviderAfterInit;
use BlueSpice\Discovery\ITemplateDataProvider;

class DiscoverySkin implements BlueSpiceDiscoveryTemplateDataProviderAfterInit {

	/**
	 * @param ITemplateDataProvider $registry
	 */
	public function onBlueSpiceDiscoveryTemplateDataProviderAfterInit( $registry ): void {
		$registry->register( 'actions_secondary', 'ca-pc_clear' );
		$registry->unregister( 'toolbox', 'ca-pc_clear' );
	}
}
