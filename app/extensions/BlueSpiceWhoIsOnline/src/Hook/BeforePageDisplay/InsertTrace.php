<?php

namespace BlueSpice\WhoIsOnline\Hook\BeforePageDisplay;

use DeferredUpdates;

class InsertTrace extends \BlueSpice\Hook\BeforePageDisplay {

	/**
	 *
	 * @return bool
	 */
	protected function doProcess() {
		$services = $this->getServices();
		$context = $this->getContext();
		DeferredUpdates::addCallableUpdate( static function () use ( $services, $context ) {
			$services->getService( 'BSWhoIsOnlineTracer' )->trace( $context );
		} );
		return true;
	}

}
