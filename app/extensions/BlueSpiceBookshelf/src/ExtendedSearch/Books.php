<?php

namespace BlueSpice\Bookshelf\ExtendedSearch;

use BlueSpice\Bookshelf\BookInfo;
use BlueSpice\Bookshelf\BookLookup;
use BlueSpice\Bookshelf\ExtendedSearch\LookupModifier\AddBookAggregation;
use BlueSpice\Bookshelf\ExtendedSearch\LookupModifier\AddSourceFields;
use BlueSpice\Bookshelf\ExtendedSearch\LookupModifier\ParseBookFilter;
use BS\ExtendedSearch\ILookupModifierProvider;
use BS\ExtendedSearch\ISearchDocumentProvider;
use BS\ExtendedSearch\ISearchSource;
use BS\ExtendedSearch\Lookup;
use BS\ExtendedSearch\Plugin\IDocumentDataModifier;
use BS\ExtendedSearch\Plugin\IFilterModifier;
use BS\ExtendedSearch\Plugin\IFormattingModifier;
use BS\ExtendedSearch\Plugin\IMappingModifier;
use BS\ExtendedSearch\Plugin\ISearchPlugin;
use BS\ExtendedSearch\SearchResult;
use BS\ExtendedSearch\Source\DocumentProvider\WikiPage as WikiPageProvider;
use BS\ExtendedSearch\Source\WikiPages;
use IContextSource;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\PageIdentity;
use Message;
use TitleFactory;
use WikiPage;

class Books implements
	ISearchPlugin,
	IMappingModifier,
	IFormattingModifier,
	IDocumentDataModifier,
	IFilterModifier,
	ILookupModifierProvider
{

	/** @var TitleFactory */
	private $titleFactory;
	/** @var LinkRenderer */
	private $linkRenderer;
	/** @var BookLookup */
	private $bookLookup;
	/** @var array */
	private $bookTitles = [];

	/**
	 * @param TitleFactory $titleFactory
	 * @param LinkRenderer $linkRenderer
	 * @param BookLookup $bookLookup
	 */
	public function __construct( TitleFactory $titleFactory, LinkRenderer $linkRenderer, BookLookup $bookLookup ) {
		$this->titleFactory = $titleFactory;
		$this->linkRenderer = $linkRenderer;
		$this->bookLookup = $bookLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function modifyMapping( ISearchSource $source, array &$indexSettings, array &$propertyMapping ): void {
		if ( !( $source instanceof WikiPages ) ) {
			return;
		}
		$propertyMapping['properties']['books'] = [
			'type' => 'keyword',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function modifyDocumentData(
		ISearchDocumentProvider $documentProvider, array &$data, $uri, $documentProviderSource
	): void {
		if ( !( $documentProvider instanceof WikiPageProvider ) ) {
			return;
		}

		$data['books'] = $this->getBooks( $documentProviderSource );
	}

	/**
	 * @inheritDoc
	 */
	public function modifyFilters(
		array &$aggregations, array &$filterCfg, array $fieldsWithANDEnabled, ISearchSource $source
	): void {
		if ( !( $source instanceof WikiPages ) ) {
			return;
		}
		if ( !isset( $aggregations['field_books'] ) ) {
			return;
		}

		$filterCfg['books'] = [
			'buckets' => $this->formatBuckets( $aggregations['field_books']['buckets'] ),
			'label' => Message::newFromKey( 'bs-bookshelf-search-center-filter-books-label' )->text(),
			'valueLabel' => Message::newFromKey( 'bs-bookshelf-search-center-filter-books-label' )->text() . ': ',
			'isANDEnabled' => 0,
			'multiSelect' => 1
		];
	}

	/**
	 * @inheritDoc
	 */
	public function formatFulltextResult(
		array &$result, SearchResult $resultObject, ISearchSource $source, Lookup $lookup
	): void {
		if ( !( $source instanceof WikiPages ) ) {
			return;
		}

		$books = $resultObject->getSourceParam( 'books' ) ?? [];
		$books = array_map( function ( $book ) {
			$this->assertBookTitle( $book );
			$bookInfo = $this->getBookInfo( $this->bookTitles[$book] );
			if ( !$bookInfo ) {
				return null;
			}
			// Show display name of the book
			return $this->linkRenderer->makeLink( $this->bookTitles[$book], $bookInfo->getName() );
		}, $books );
		// remove nulls
		$books = array_filter( $books );
		if ( empty( $books ) ) {
			return;
		}
		$msg = Message::newFromKey( 'bs-bookshelf-search-center-result-books-label', count( $books ) )->text();
		$result['books'] = $msg . ': ' . implode( ', ', $books );
	}

	/**
	 * @inheritDoc
	 */
	public function formatAutocompleteResults( array &$results, array $searchData ): void {
		// NOOP
	}

	/**
	 * @inheritDoc
	 */
	public function modifyResultStructure( array &$resultStructure, ISearchSource $source ): void {
		if ( !( $source instanceof WikiPages ) ) {
			return;
		}
		$resultStructure['secondaryInfos']['bottom']['items'][] = [
			"name" => "books",
			"nolabel" => 1
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getLookupModifiers( Lookup $lookup, IContextSource $context ): array {
		return [
			new AddBookAggregation( $lookup, $context ),
			new AddSourceFields( $lookup, $context ),
			new ParseBookFilter( $lookup, $context, $this->bookLookup ),
		];
	}

	/**
	 * @param mixed $documentProviderSource
	 *
	 * @return array
	 */
	private function getBooks( $documentProviderSource ): array {
		if ( !( $documentProviderSource instanceof WikiPage ) ) {
			return [];
		}
		$books = $this->bookLookup->getBooksForPage( $documentProviderSource->getTitle() );
		// Book prefixed db keys
		return array_keys( $books );
	}

	/**
	 * @param array $buckets
	 *
	 * @return array
	 */
	private function formatBuckets( array $buckets ) {
		foreach ( $buckets as &$bucket ) {
			$this->assertBookTitle( $bucket['key'] );
			$bookInfo = $this->getBookInfo( $this->bookTitles[$bucket['key']] );
			if ( !$bookInfo ) {
				continue;
			}
			$bucket['key'] = $bookInfo->getName();
		}

		return $buckets;
	}

	/**
	 * @param string $book
	 *
	 * @return void
	 */
	private function assertBookTitle( string $book ) {
		if ( !isset( $this->bookTitles[$book] ) ) {
			$bookTitle = $this->titleFactory->newFromText( $book, NS_BOOK );
			if ( $bookTitle ) {
				$this->bookTitles[$book] = $bookTitle;
			}
		}
	}

	/**
	 * @param PageIdentity|null $page
	 *
	 * @return BookInfo|null
	 */
	private function getBookInfo( ?PageIdentity $page ): ?BookInfo {
		if ( !$page ) {
			return null;
		}
		return $this->bookLookup->getBookInfo( $page );
	}
}
