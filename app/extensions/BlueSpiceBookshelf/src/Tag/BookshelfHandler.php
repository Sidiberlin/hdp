<?php

namespace BlueSpice\Bookshelf\Tag;

use BlueSpice\Tag\Handler;
use MediaWiki\Config\Config;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;

class BookshelfHandler extends Handler {

	/**
	 * @param string $processedInput
	 * @param array $processedArgs
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param Config $config
	 */
	public function __construct( $processedInput, array $processedArgs, Parser $parser,
		PPFrame $frame, Config $config ) {
		parent::__construct( $processedInput, $processedArgs, $parser, $frame );
	}

	/**
	 * @return string
	 */
	public function handle() {
		return '';
	}

}
