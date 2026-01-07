<?php

use MediaWiki\MediaWikiServices;
use SimpleTasks\SimpleTaskManager;

return [
	'SimpleTaskManager' => static function ( MediaWikiServices $services ) {
		return new SimpleTaskManager(
			$services->getDBLoadBalancer(),
			$services->getService( 'ChecklistManager' ),
			$services->getUserFactory(),
			$services->getService( 'AtMentionsParser' ),
			$services->getService( 'DateTimeToolParser' ),
			$services->getService( 'MWStake.Notifier' ),
			$services->getContentLanguage()
		);
	},
];
