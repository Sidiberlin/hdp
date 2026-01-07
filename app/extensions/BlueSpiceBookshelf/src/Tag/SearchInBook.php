<?php

namespace BlueSpice\Bookshelf\Tag;

use BlueSpice\ParamProcessor\IParamDefinition;
use BlueSpice\ParamProcessor\ParamDefinition;
use BlueSpice\ParamProcessor\ParamType;
use BlueSpice\Tag\IHandler;
use BS\ExtendedSearch\Tag\TagSearch;
use MediaWiki\Config\ConfigException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;

class SearchInBook extends TagSearch {

	/**
	 * @param mixed $processedInput
	 * @param array $processedArgs
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return IHandler
	 * @throws ConfigException
	 */
	public function getHandler(
		$processedInput,
		array $processedArgs,
		Parser $parser,
		PPFrame $frame
	) {
		$config = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'bsg' );

		return new SearchInBookHandler(
			$processedInput,
			$processedArgs,
			$parser,
			$frame,
			$config,
			$this->nextTagId(),
			MediaWikiServices::getInstance()->getService( 'BSBookshelfBookLookup' )
		);
	}

	/**
	 * @return array|string[]
	 */
	public function getTagNames() {
		return [ 'bs:searchinbook' ];
	}

	/**
	 * @return IParamDefinition[]
	 */
	public function getArgsDefinitions() {
		$params = parent::getArgsDefinitions();
		$params[] = new ParamDefinition(
			ParamType::STRING,
			'book',
			Message::newFromKey( 'bs-bookshelf-tag-search-in-books-book' )->plain()
		);
		return $params;
	}
}
