<?php

use BlueSpice\MatomoConnector\CustomDimensions;
use MediaWiki\MediaWikiServices;

return [
	'BlueSpiceMatomoConnectorDimensions' => static function ( MediaWikiServices $services ) {
		return new CustomDimensions(
			$services->getConfigFactory(),
			$services->getObjectCacheFactory(),
			$services->getHttpRequestFactory()
		);
	}
];
