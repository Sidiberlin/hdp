<?php

use BlueSpice\Bookshelf\BookContextProviderFactory;
use BlueSpice\Bookshelf\BookLookup;
use BlueSpice\Bookshelf\BookMetaLookup;
use BlueSpice\Bookshelf\ChapterLookup;
use BlueSpice\Bookshelf\Renderer\ComponentRenderer;
use BlueSpice\Bookshelf\TreeParser;
use BlueSpice\ExtensionAttributeBasedRegistry;
use MediaWiki\MediaWikiServices;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in ServiceWiringTest.php
// @codeCoverageIgnoreStart

return [
	'BSBookshelfTreeParser' => static function ( MediaWikiServices $services ) {
		$lineParsers = new ExtensionAttributeBasedRegistry( 'BlueSpiceBookshelfLineProcessors' );
		return new TreeParser(
			$services->getConfigFactory()->makeConfig( 'bsg' ),
			$lineParsers
		);
	},
	'BSBookshelfPageHierarchyProviderFactory' => static function ( MediaWikiServices $services ) {
		return new \BlueSpice\Bookshelf\PageHierarchyProviderFactory();
	},
	'BSBookshelfComponentRenderer' => static function ( MediaWikiServices $services ) {
		$renderer = new ComponentRenderer(
			$services->getService( 'MWStakeCommonUIComponentManager' ),
			$services->getService( 'MWStakeCommonUIRendererDataTreeBuilder' ),
			$services->getService( 'MWStakeCommonUIRendererDataTreeRenderer' )
		);
		return $renderer;
	},
	'BSBookshelfBookLookup' => static function ( MediaWikiServices $services ) {
		$provider = new BookLookup(
			$services->getTitleFactory(),
			$services->getDBLoadBalancer(),
			$services->getService( 'BSBookshelfBookChapterLookup' )
		);
		return $provider;
	},
	'BSBookshelfBookChapterLookup' => static function ( MediaWikiServices $services ) {
		$provider = new ChapterLookup(
			$services->getDBLoadBalancer(),
			$services->getTitleFactory(),
			$services->getConfigFactory()
		);
		return $provider;
	},
	'BSBookshelfBookMetaLookup' => static function ( MediaWikiServices $services ) {
		$provider = new BookMetaLookup(
			$services->getDBLoadBalancer(),
			$services->getService( 'BSBookshelfBookLookup' )
		);
		return $provider;
	},
	'BSBookshelfBookContextProviderFactory' => static function ( MediaWikiServices $services ) {
		return new BookContextProviderFactory(
			$services->getObjectFactory(),
			$services->getService( 'BSBookshelfBookLookup' ),
			$services->getTitleFactory()
		);
	},
	'BSBookshelfChapterUpdater' => static function ( MediaWikiServices $services ) {
		return new \BlueSpice\Bookshelf\ChapterUpdater(
			$services->getDBLoadBalancer(),
			$services->getService( 'BSBookshelfBookLookup' ),
			$services->getHookContainer(),
			$services->getTitleFactory()
		);
	},
];

// @codeCoverageIgnoreEnd
