<?php

namespace BlueSpice\Bookshelf\Property;

use BlueSpice\Bookshelf\BookLookup;
use BlueSpice\SMWConnector\PropertyValueProvider;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use SESP\AppFactory;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\SemanticData;
use SMWDataItem;

class BooksPropertyValueProvider extends PropertyValueProvider {

	/** @var BookLookup */
	protected $lookup;

	/**
	 * @param BookLookup $lookup
	 */
	public function __construct( BookLookup $lookup ) {
		$this->lookup = $lookup;
	}

	public static function factory() {
		return [ new static( MediaWikiServices::getInstance()->getService( 'BSBookshelfBookLookup' ) ) ];
	}

	/** @inheritDoc */
	public function getAliasMessageKey() {
		return "bs-bookshelf-sesp-books";
	}

	/** @inheritDoc */
	public function getDescriptionMessageKey() {
		return "bs-bookshelf-sesp-books-desc";
	}

	/** @inheritDoc */
	public function getType() {
		return SMWDataItem::TYPE_WIKIPAGE;
	}

	/** @inheritDoc */
	public function getId() {
		return '_BS_BOOKSHELF_BOOKS';
	}

	/** @inheritDoc */
	public function getLabel() {
		return "Books";
	}

	/**
	 * @param AppFactory $appFactory
	 * @param DIProperty $property
	 * @param SemanticData $semanticData
	 * @return void
	 */
	public function addAnnotation( $appFactory, $property, $semanticData ) {
		$title = $semanticData->getSubject()->getTitle();
		if ( !$title ) {
			return;
		}
		$bookDataModels = $this->lookup->getBooksForPage( $title );
		if ( !$bookDataModels ) {
			return;
		}

		foreach ( $bookDataModels as $bookPrefixedText => $bookDataModel ) {
			$bookTitle = Title::newFromText( $bookPrefixedText );
			if ( !$title ) {
				continue;
			}

			$semanticData->addPropertyObjectValue(
				$property,
				DIWikiPage::newFromTitle( $bookTitle )
			);
		}
	}

}
