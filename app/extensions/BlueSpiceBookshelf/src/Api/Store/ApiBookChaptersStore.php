<?php

namespace BlueSpice\Bookshelf\Api\Store;

use BlueSpice\Bookshelf\Data\BookChapters\Store;
use BlueSpice\Context;
use RequestContext;

class ApiBookChaptersStore extends \BlueSpice\Api\Store {

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
			$this->services->getDBLoadBalancer()
		);
	}
}
