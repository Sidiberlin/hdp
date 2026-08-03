<?php

namespace BlueSpice\Bookshelf\Rest;

use BlueSpice\Bookshelf\BookLookup;
use BlueSpice\Bookshelf\BookMetaLookup;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class GetBookInfo extends SimpleHandler {

	/**
	 *
	 * @param TitleFactory $titleFactory
	 * @param BookLookup $bookLookup
	 * @param BookMetaLookup $bookMetaLookup
	 */
	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly BookLookup $bookLookup,
		private readonly BookMetaLookup $bookMetaLookup
	) {
	}

	/**
	 * @return Response
	 */
	public function run() {
		$bookTitle = $this->getBookTitle();
		if ( !$bookTitle ) {
			return $this->getResponseFactory()->createHttpError( 404, [ 'No valid book title' ] );
		}
		$info = $this->bookLookup->getBookInfo( $bookTitle );
		if ( !$info ) {
			return $this->getResponseFactory()->createHttpError( 404, [ 'Book not found' ] );
		}
		return $this->getResponseFactory()->createJson( array_merge(
			$info->jsonSerialize(),
			[ 'meta' => $this->getBookMeta( $bookTitle ) ]
		) );
	}

	/**
	 * @return Title|null
	 */
	protected function getBookTitle(): ?Title {
		$validated = $this->getValidatedParams();
		$bookName = $validated[ 'booktitle' ];
		return $this->titleFactory->newFromText( $bookName );
	}

	/**
	 * @param Title $bookTitle
	 * @return array
	 */
	protected function getBookMeta( Title $bookTitle ): array {
		return $this->bookMetaLookup->getMetaForBook( $bookTitle );
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		return [
			'booktitle' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}
}
