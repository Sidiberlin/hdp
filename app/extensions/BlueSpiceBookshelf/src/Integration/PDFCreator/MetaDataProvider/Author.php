<?php

namespace BlueSpice\Bookshelf\Integration\PDFCreator\MetaDataProvider;

use BlueSpice\Bookshelf\BookMetaLookup;
use MediaWiki\Extension\PDFCreator\IMetaDataProvider;
use MediaWiki\Extension\PDFCreator\Utility\ExportContext;
use MediaWiki\Extension\PDFCreator\Utility\HtmlMetaItem;
use MediaWiki\Title\TitleFactory;

class Author implements IMetaDataProvider {

	/** @var BookMetaLookup */
	private $bookMetaLookup;

	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param BookMetaLookup $bookMetaLookup
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( BookMetaLookup $bookMetaLookup, TitleFactory $titleFactory ) {
		$this->bookMetaLookup = $bookMetaLookup;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function execute( string $module, ExportContext $context ): array {
		if ( $module !== 'book' ) {
			return [];
		}
		$pageIdentity = $context->getPageIdentity();
		if ( !$pageIdentity ) {
			return [];
		}
		$bookTitle = $this->titleFactory->newFromPageIdentity( $pageIdentity );
		$author = $this->bookMetaLookup->getMetaValueForBook( $bookTitle, 'author1' );
		if ( empty( $author ) ) {
			return [];
		}

		return [ new HtmlMetaItem( 'Author', $author ) ];
	}

}
