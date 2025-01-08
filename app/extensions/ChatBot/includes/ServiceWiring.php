<?php

use ChatBot\AdminModuleFactory;
use ChatBot\DeepsetApi\ChatApi;
use ChatBot\DeepsetApi\FeedbackApi;
use ChatBot\DeepsetApi\HistoryApi;
use ChatBot\DeepsetApi\IndexApi;
use ChatBot\DeepsetApi\ListApi;
use ChatBot\DeepsetApi\SessionApi;
use ChatBot\Model\ChatMessageFactory;
use ChatBot\Util\RoleLookup;
use MediaWiki\MediaWikiServices;

return [
	'BmbfChatMessageFactory' => static function ( MediaWikiServices $services ) {
		return new ChatMessageFactory();
	},
	'BmbfRoleLookup' => static function ( MediaWikiServices $services ) {
		return new RoleLookup(
			$services->getUserGroupManager()
		);
	},
	'DeepsetChatApi' => static function ( MediaWikiServices $services ) {
		return new ChatApi(
			$services->getService( 'MainConfig' ),
			$services->getHttpRequestFactory(),
			$services->getService( 'BmbfRoleLookup' )
		);
	},
	'DeepsetSessionApi' => static function ( MediaWikiServices $services ) {
		return new SessionApi(
			$services->getService( 'MainConfig' ), $services->getHttpRequestFactory()
		);
	},
	'DeepsetHistoryApi' => static function ( MediaWikiServices $services ) {
		return new HistoryApi(
			$services->getService( 'MainConfig' ), $services->getHttpRequestFactory()
		);
	},
	'DeepsetListApi' => static function ( MediaWikiServices $services ) {
		return new ListApi(
			$services->getService( 'MainConfig' ), $services->getHttpRequestFactory()
		);
	},
	'DeepsetIndexApi' => static function ( MediaWikiServices $services ) {
		return new IndexApi(
			$services->getService( 'MainConfig' ),
			$services->getHttpRequestFactory(),
			$services->getService( 'DeepsetListApi' )
		);
	},
	'DeepsetFeedbackApi' => static function ( MediaWikiServices $services ) {
		return new FeedbackApi(
			$services->getService( 'MainConfig' ), $services->getHttpRequestFactory()
		);
	},
	'Deepset.AdminModuleFactory' => static function ( MediaWikiServices $services ) {
		return new AdminModuleFactory(
			ExtensionRegistry::getInstance()->getAttribute( 'ChatBotAdminModule' ),
			$services->getObjectFactory()
		);
	},
];
