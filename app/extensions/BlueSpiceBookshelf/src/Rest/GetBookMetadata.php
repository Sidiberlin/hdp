<?php

namespace BlueSpice\Bookshelf\Rest;

class GetBookMetadata extends GetBookInfo {

	/**
	 * @inheritDoc
	 */
	public function run() {
		$bookTitle = $this->getBookTitle();
		if ( !$bookTitle ) {
			return $this->getResponseFactory()->createHttpError( 404, [ 'No valid book title' ] );
		}
		return $this->getResponseFactory()->createJson( $this->getBookMeta( $bookTitle ) );
	}
}
