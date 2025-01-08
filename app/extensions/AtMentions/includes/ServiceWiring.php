<?php

use AtMentions\MentionParser;
use AtMentions\MentionStore;
use MediaWiki\MediaWikiServices;

return [
	'AtMentionsParser' => static function ( MediaWikiServices $services ) {
		return new MentionParser(
			$services->getUserFactory(),
			$services->getContentLanguage()
		);
	},
	'AtMentionsStore' => static function ( MediaWikiServices $services ) {
		return new MentionStore(
			$services->getDBLoadBalancer(),
			$services->getUserFactory(),
			$services->getRevisionStore()
		);
	},
];
