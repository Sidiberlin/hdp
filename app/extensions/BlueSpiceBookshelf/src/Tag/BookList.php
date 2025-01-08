<?php

namespace BlueSpice\Bookshelf\Tag;

use BlueSpice\ParamProcessor\IParamDefinition;
use BlueSpice\ParamProcessor\ParamDefinition;
use BlueSpice\ParamProcessor\ParamType;
use BlueSpice\Tag\MarkerType;
use BlueSpice\Tag\MarkerType\NoWiki;
use BlueSpice\Tag\Tag;
use MediaWiki\MediaWikiServices;
use Parser;
use PPFrame;

class BookList extends Tag {

	public const PARAM_FILTER = 'filter';

	/**
	 *
	 * @return bool
	 */
	public function needsDisabledParserCache() {
		return true;
	}

	/**
	 *
	 * @return bool
	 */
	public function needsParsedInput() {
		return false;
	}

	/**
	 *
	 * @return bool
	 */
	public function needsParseArgs() {
		return false;
	}

	/**
	 *
	 * @return MarkerType
	 */
	public function getMarkerType() {
		return new NoWiki();
	}

	/**
	 *
	 * @param string $processedInput
	 * @param array $processedArgs
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return PageBreakHandler
	 */
	public function getHandler( $processedInput, array $processedArgs, Parser $parser,
		PPFrame $frame ) {
		$services = MediaWikiServices::getInstance();
		return new BookListHandler(
			$processedInput,
			$processedArgs,
			$parser,
			$frame,
			$services->getTitleFactory(),
			$services->getDBLoadBalancer(),
			$services->getLinkRenderer(),
			$services->getService( 'BSBookshelfBookLookup' ),
			$services->getService( 'BSBookshelfBookMetaLookup' )
		);
	}

	/**
	 *
	 * @return string[]
	 */
	public function getTagNames() {
		return [
			'booklist',
			'bs:booklist',
		];
	}

	/**
	 * @return IParamDefinition[]
	 */
	public function getArgsDefinitions() {
		$filter = new ParamDefinition(
			ParamType::STRING,
			static::PARAM_FILTER,
			''
		);
		$filter->setArrayValues( [ 'hastoexist' => true ] );
		return [ $filter ];
	}
}
