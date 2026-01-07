<?php

use MediaWiki\Extension\CollabPads\CollabRevisionManager;
use MediaWiki\Extension\CollabPads\CollabSessionManager;
use MediaWiki\MediaWikiServices;

return [
	'CollabPadsCollabSessionManager' => static function ( MediaWikiServices $services ) {
		return new CollabSessionManager(
			$services->getConnectionProvider(),
			$services->getObjectCacheFactory()
		);
	},
	'CollabPadsCollabRevisionManager' => static function ( MediaWikiServices $services ) {
		return new CollabRevisionManager( $services->getDBLoadBalancer() );
	}
];
