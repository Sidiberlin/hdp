<?php

return [
	'DateTimeToolParser' => static function ( \MediaWiki\MediaWikiServices $services ) {
		return new \MediaWiki\Extension\DateTimeTools\DateTimeParser();
	},
];
