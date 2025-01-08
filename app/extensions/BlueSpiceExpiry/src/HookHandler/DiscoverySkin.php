<?php

namespace BlueSpice\Expiry\HookHandler;

use BlueSpice\Discovery\Hook\BlueSpiceDiscoveryTemplateDataProviderAfterInit;
use BlueSpice\Discovery\ITemplateDataProvider;

class DiscoverySkin implements BlueSpiceDiscoveryTemplateDataProviderAfterInit {

	/**
	 *
	 * @param ITemplateDataProvider $registry
	 * @return void
	 */
	public function onBlueSpiceDiscoveryTemplateDataProviderAfterInit( $registry ): void {
		$registry->unregister( 'toolbox', 'ca-expiryCreate' );
		$registry->register( 'actions_secondary', 'ca-expiryCreate' );
	}
}
