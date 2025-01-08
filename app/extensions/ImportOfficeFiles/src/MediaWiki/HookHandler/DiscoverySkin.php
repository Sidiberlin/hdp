<?php

namespace MediaWiki\Extension\ImportOfficeFiles\MediaWiki\HookHandler;

use BlueSpice\Discovery\Hook\BlueSpiceDiscoveryTemplateDataProviderAfterInit;

class DiscoverySkin implements BlueSpiceDiscoveryTemplateDataProviderAfterInit {

	/**
	 * @inheritDoc
	 */
	public function onBlueSpiceDiscoveryTemplateDataProviderAfterInit( $registry ): void {
		$registry->register( 'panel/create', 'ca-import-office-file' );
	}
}
