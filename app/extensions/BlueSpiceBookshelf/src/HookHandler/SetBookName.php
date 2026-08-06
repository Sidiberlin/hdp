<?php

namespace BlueSpice\Bookshelf\HookHandler;

use BlueSpice\Bookshelf\BookLookup;
use MediaWiki\Hook\BeforePageDisplayHook;

class SetBookName implements BeforePageDisplayHook {

	/**
	 * @param BookLookup $book_lookup
	 */
	public function __construct( private readonly BookLookup $book_lookup ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$title = $out->getTitle();
		if ( !$title ) {
			return;
		}

		if ( $title->getContentModel() !== 'book' ) {
			return;
		}
		$bookInfo = $this->book_lookup->getBookInfo( $title );
		if ( !$bookInfo ) {
			return;
		}
		$bookName = $bookInfo->getName();
		$out->setPageTitle( $bookName );
	}
}
