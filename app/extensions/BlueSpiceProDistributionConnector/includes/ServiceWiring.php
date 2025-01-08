<?php

use BlueSpice\ProDistributionConnector\EditionProvider;
use BlueSpice\ProDistributionConnector\UserCounter;
use MediaWiki\MediaWikiServices;

return [
	'BlueSpiceUserCounter' => static function ( MediaWikiServices $services ) {
		return new UserCounter(
			$services->getConfigFactory()->makeConfig( 'bsg' ),
			$services->getMainConfig(),
			$services->getDBLoadBalancer(),
			$services->getService( 'BlueSpiceEditionProvider' )
		);
	},
	'BlueSpiceEditionProvider' => static function ( MediaWikiServices $services ) {
		return new EditionProvider();
	},
];
