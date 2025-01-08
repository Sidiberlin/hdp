<?php

use MediaWiki\MediaWikiServices;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in ServiceWiringTest.php
// @codeCoverageIgnoreStart

return [
	'BSReadConfirmationMechanismFactory' => static function ( MediaWikiServices $services ) {
		$mechanismCallback = $services->getConfigFactory()
			->makeConfig( 'bsg' )->get( 'ReadConfirmationMechanism' );

		return new \BlueSpice\ReadConfirmation\MechanismFactory( $mechanismCallback );
	},
];

// @codeCoverageIgnoreEnd
