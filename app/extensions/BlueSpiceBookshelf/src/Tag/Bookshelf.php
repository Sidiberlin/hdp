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

class Bookshelf extends Tag {

	public const PARAM_SRC = 'src';
	public const PARAM_BOOK = 'book';
	public const PARAM_WIDTH = 'width';
	public const PARAM_HEIGHT = 'height';
	public const PARAM_FLOAT = 'float';
	public const PARAM_STYLE = 'style';

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
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'bsg' );
		return new BookshelfHandler(
			$processedInput,
			$processedArgs,
			$parser,
			$frame,
			$config
		);
	}

	/**
	 *
	 * @return string[]
	 */
	public function getTagNames() {
		return [
			'bs:bookshelf',
			'bookshelf',
			'htoc',
			'hierachicaltoc',
		];
	}

	/**
	 * @return IParamDefinition[]
	 */
	public function getArgsDefinitions() {
		return [
			new ParamDefinition(
				ParamType::STRING,
				static::PARAM_SRC,
				''
			),
			new ParamDefinition(
				ParamType::STRING,
				static::PARAM_BOOK,
				''
			),
			new ParamDefinition(
				ParamType::INTEGER,
				static::PARAM_WIDTH,
				300
			),
			new ParamDefinition(
				ParamType::INTEGER,
				static::PARAM_HEIGHT,
				400
			),
			new ParamDefinition(
				ParamType::STRING,
				static::PARAM_FLOAT,
				''
			),
			new ParamDefinition(
				ParamType::STRING,
				static::PARAM_STYLE,
				''
			),
		];
	}

}
