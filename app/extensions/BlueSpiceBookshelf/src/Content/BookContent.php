<?php

namespace BlueSpice\Bookshelf\Content;

use MWException;
use TextContent;

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
