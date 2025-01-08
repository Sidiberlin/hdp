<?php

namespace BlueSpice\Bookshelf\Tag;

use BlueSpice\Bookshelf\BookLookup;
use BS\ExtendedSearch\Lookup;
use BS\ExtendedSearch\Tag\TagSearchHandler;
use Config;
use Exception;
use Message;
use Parser;
use PPFrame;

class SearchInBookHandler extends TagSearchHandler {

	/** @var BookLookup */
	private $bookLookup;

	/**
	 * @param string $processedInput
	 * @param array $processedArgs
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param Config $config
	 * @param int $tagIdNumber
	 * @param BookLookup $bookLookup
	 */
	public function __construct(
		$processedInput, array $processedArgs, $parser, PPFrame $frame,
		Config $config, $tagIdNumber, BookLookup $bookLookup
	) {
		parent::__construct( $processedInput, $processedArgs, $parser, $frame, $config, $tagIdNumber );
		$this->bookLookup = $bookLookup;
	}

	/**
	 * @param Lookup $lookup
	 * @return void
	 * @throws Exception
	 */
	protected function modifyLookup( Lookup $lookup ) {
		$bookPrefixed = $this->getBookFilterValue( $this->processedArgs['book'] );
		if ( !$bookPrefixed ) {
			throw new Exception(
				Message::newFromKey( 'bs-bookshelf-tag-searchinbook-error', $this->processedArgs['book'] )->text()
			);
		}
		$lookup->addTermsFilter( 'books', [ $bookPrefixed ] );
	}

	/**
	 * @param mixed $book
	 * @return string|null
	 */
	private function getBookFilterValue( mixed $book ): ?string {
		if ( !$book || !is_string( $book ) ) {
			return null;
		}

		$title = $this->bookLookup->getBookTitleFromName( $book );
		if ( !$title ) {
			return null;
		}
		return $title->getPrefixedDBkey();
	}

}
