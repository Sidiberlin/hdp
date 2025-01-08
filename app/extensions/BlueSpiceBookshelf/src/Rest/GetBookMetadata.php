<?php

namespace BlueSpice\Bookshelf\Rest;

use BlueSpice\Bookshelf\BookMetaLookup;
use MediaWiki\Rest\SimpleHandler;
use TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class GetBookMetadata extends SimpleHandler {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var BookMetaLookup */
	private $lookup;

	/**
	 *
	 * @param TitleFactory $titleFactory
	 * @param BookMetaLookup $lookup
	 */
	public function __construct( TitleFactory $titleFactory, BookMetaLookup $lookup ) {
		$this->titleFactory = $titleFactory;
		$this->lookup = $lookup;
	}

	/**
	 * @inheritDoc
	 */
	public function run() {
		$validated = $this->getValidatedParams();
		$bookName = $validated[ 'booktitle' ];
		$bookTitle = $this->titleFactory->newFromText( $bookName );
		if ( !$bookTitle ) {
			return $this->getResponseFactory()->createHttpError( 404, [ 'No valid book title' ] );
		}
		$data = $this->lookup->getMetaForBook( $bookTitle );
		return $this->getResponseFactory()->createJson( $data );
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
