<?php

namespace BlueSpice\Bookshelf\HookHandler;

use BlueSpice\Discovery\Hook\BlueSpiceDiscoveryTemplateDataProviderAfterInit;
use BlueSpice\Discovery\ITemplateDataProvider;

class DiscoverySkin implements BlueSpiceDiscoveryTemplateDataProviderAfterInit {

	/**
	 *
	 * @param ITemplateDataProvider $registry
	 * @return void
	 */
	public function onBlueSpiceDiscoveryTemplateDataProviderAfterInit( $registry ): void {
		$registry->unregister( 'toolbox', 'ca-bookshelf-add-to-book' );
		$registry->register( 'actions_secondary', 'ca-bookshelf-add-to-book' );
		$registry->register( 'panel/edit', 'ca-editbooksource' );

		$registry->register( 'actions_primary', 'ca-bookshelf-create-new-book' );
		$registry->register( 'panel/create', 'ca-bookshelf-create-book' );
	}

}
