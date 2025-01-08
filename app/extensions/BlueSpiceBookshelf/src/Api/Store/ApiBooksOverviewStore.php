<?php

namespace BlueSpice\Bookshelf\Api\Store;

use BlueSpice\Bookshelf\Data\BooksOverview\Store;
use BlueSpice\Context;
use RequestContext;

/**
 * Api class for
 * <mediawiki>/api.php?action=bs-books-overview-store
 */
class ApiBooksOverviewStore extends \BlueSpice\Api\Store {

	/**
	 *
	 * @return string[]
	 */
	protected function getRequiredPermissions() {
		return [ 'read' ];
	}

	/**
	 *
	 * @return Store
	 */
	protected function makeDataStore() {
		return new Store(
			new Context( RequestContext::getMain(), $this->getConfig() ),
			$this->getConfig(),
			$this->services->getDBLoadBalancer(),
			$this->services->getService( 'BSBookshelfBookChapterLookup' ),
			$this->services->getService( 'BSBookshelfBookMetaLookup' ),
			$this->services->getTitleFactory(),
			$this->services->getPermissionManager(),
			$this->services->getHookContainer(),
			$this->services->getRepoGroup(),
		);
	}
}
