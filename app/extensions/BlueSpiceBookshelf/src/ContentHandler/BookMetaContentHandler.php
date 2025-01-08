<?php

namespace BlueSpice\Bookshelf\ContentHandler;

use JsonContentHandler;

class BookMetaContentHandler extends JsonContentHandler {

	/**
	 * @param string $modelId
	 */
	public function __construct( $modelId = 'book_meta' ) {
		parent::__construct( $modelId, [ CONTENT_FORMAT_JSON ] );
	}

		/**
		 * @return array
		 */
	public function getActionOverrides() {
		return [];
	}

}
