<?php

namespace BlueSpice\Bookshelf\Tag;

use BlueSpice\Bookshelf\BookContextProviderFactory;
use BlueSpice\Bookshelf\BookLookup;
use BlueSpice\ParamProcessor\ParamDefinition;
use BlueSpice\ParamProcessor\ParamType;
use BlueSpice\Tag\Tag;
use MediaWiki\MediaWikiServices;
use MWStake\MediaWiki\Component\CommonUserInterface\TreeDataGenerator;
use Parser;
use PPFrame;

class BookNav extends Tag {

	public const ATTR_BOOK = 'book';
	public const ATTR_CHAPTER = 'chapter';

	/** @var MediaWikiServices */
	private $services;

	public function __construct() {
		$this->services = MediaWikiServices::getInstance();
	}

	/**
	 * @param string $processedInput
	 * @param array $processedArgs
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return PageBreakHandler
	 */
	public function getHandler( $processedInput, array $processedArgs, Parser $parser, PPFrame $frame ) {
		$titleFactory = $this->services->getTitleFactory();
		$componentRenderer = $this->services->getService( 'BSBookshelfComponentRenderer' );

		/** @var BookContextProviderFactory */
		$bookContxtProviderFactory = $this->services->getService( 'BSBookshelfBookContextProviderFactory' );
		/** @var BookLookup */
		$bookLookup = $this->services->getService( 'BSBookshelfBookLookup' );
		/** @var TreeDataGenerator */
		$treeDataGenerator = $this->services->getService( 'MWStakeCommonUITreeDataGenerator' );

		return new BookNavHandler(
			$processedInput,
			$processedArgs,
			$parser,
			$frame,
			$titleFactory,
			$componentRenderer,
			$bookContxtProviderFactory,
			$bookLookup,
			$treeDataGenerator
		);
	}

	/**
	 * @return string[]
	 */
	public function getTagNames() {
		return [
			'booknav'
		];
	}

	/**
	 * @return IParamDefinition[]
	 */
	public function getArgsDefinitions() {
		return [
			new ParamDefinition(
			ParamType::STRING,
			static::ATTR_BOOK,
			''
			),
		new ParamDefinition(
			ParamType::STRING,
			static::ATTR_CHAPTER,
			''
			)
		];
	}
}
