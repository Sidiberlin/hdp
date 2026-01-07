<?php

namespace BlueSpice\Bookshelf\Rest;

use BlueSpice\Bookshelf\BookMetaLookup;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class GetTemplateForBook extends SimpleHandler {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var BookMetaLookup */
	private $lookup;

	/** @var ConfigFactory */
	private $configFactory;

	/**
	 *
	 * @param TitleFactory $titleFactory
	 * @param BookMetaLookup $lookup
	 * @param ConfigFactory $configFactory
	 */
	public function __construct( TitleFactory $titleFactory, BookMetaLookup $lookup, ConfigFactory $configFactory ) {
		$this->titleFactory = $titleFactory;
		$this->lookup = $lookup;
		$this->configFactory = $configFactory;
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

		$template = $this->lookup->getMetaValueForBook( $bookTitle, 'pdftemplate' );
		if ( $this->templateExists( $template ) ) {
			return $this->getResponseFactory()->createJson( [
				'template' => $template,
				'pageid' => $bookTitle->getArticleID()
			] );
		}

		$template = $this->getTemplateFromConfig();
		if ( $this->templateExists( $template ) ) {
			return $this->getResponseFactory()->createJson( [
				'template' => $template,
				'pageid' => $bookTitle->getArticleID()
			] );
		}

		return $this->getResponseFactory()->createHttpError( 404, [ 'No default template for book found' ] );
	}

	/**
	 * Check if template page exists.
	 *
	 * @param string $template
	 *
	 * @return bool
	 */
	private function templateExists( string $template ): bool {
		if ( !$template ) {
			return false;
		}

		$templateTitle = $this->titleFactory->newFromText( 'MediaWiki:PDFCreator/' . $template );
		if ( !$templateTitle->exists() ) {
			return false;
		}

		return true;
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

	private function getTemplateFromConfig(): string {
		$bsgConfig = $this->configFactory->makeConfig( 'bsg' );
		$template = $bsgConfig->get( 'BookshelfDefaultBookTemplate' );
		$templateTitle = $this->titleFactory->newFromText( 'MediaWiki:PDFCreator/' . $template );
		if ( !$templateTitle->exists() ) {
			return '';
		}
		return $template;
	}
}
