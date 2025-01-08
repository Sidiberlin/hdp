<?php

use BlueSpice\Expiry\SpecialLogLogger;
use MediaWiki\MediaWikiServices;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in ServiceWiringTest.php
// @codeCoverageIgnoreStart

return [

	'BSExpiryFactory' => static function ( MediaWikiServices $services ) {
		return new \BlueSpice\Expiry\Factory;
	},

	'BSExpirySpecialLogLogger' => static function ( MediaWikiServices $services ) {
		return new SpecialLogLogger();
	},
];

// @codeCoverageIgnoreEnd
