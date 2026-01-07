<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MergeArticles\PageFilterFactory;

return [
	'MergeArticlesPageFilterFactory' => static function ( MediaWikiServices $services ) {
		return new PageFilterFactory(
			ExtensionRegistry::getInstance()->getAttribute( 'MergeArticlesPageFilters' ),
			RequestContext::getMain()
		);
	}
];
