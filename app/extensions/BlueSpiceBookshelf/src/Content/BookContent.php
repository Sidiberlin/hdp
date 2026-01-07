<?php

namespace BlueSpice\Bookshelf\Content;

use MediaWiki\Content\TextContent;
use MWException;

class BookContent extends TextContent {
	/**
	 * @param string $text
	 * @param string $model_id
	 * @throws MWException
	 */
	public function __construct( $text, $model_id = 'book' ) {
		parent::__construct( $text, $model_id );
	}
}
