<?php

namespace BlueSpice\Bookshelf\BooksOverviewActions;

use BlueSpice\Bookshelf\IBooksOverviewAction;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;

class BookMetaData implements IBooksOverviewAction {

	/**
	 * @var Title
	 */
	private $book = null;

	/**
	 * @var string
	 */
	private $displayTitle = '';

	/**
	 * @param Title $book
	 * @param string $displayTitle
	 */
	public function __construct( Title $book, string $displayTitle ) {
		$this->book = $book;
		$this->displayTitle = $displayTitle;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return 'book_meta';
	}

	/**
	 * @return int
	 */
	public function getPosition(): int {
		return 60;
	}

	/**
	 * @return array
	 */
	public function getClasses(): array {
		return [ 'bs-books-overview-action-metadata' ];
	}

	/**
	 * @return array
	 */
	public function getIconClasses(): array {
		return [ 'bi-book' ];
	}

	/**
	 * @return Message
	 */
	public function getText(): Message {
		return new Message( 'bs-books-overview-page-book-action-metadata-text' );
	}

	/**
	 * @return Message
	 */
	public function getTitle(): Message {
		$titleText = $this->book->getPrefixedText();
		if ( $this->displayTitle !== '' ) {
			$titleText = $this->displayTitle;
		}
		return new Message( 'bs-books-overview-page-book-action-metadata-title', [ $titleText ] );
	}

	/**
	 * @return string
	 */
	public function getHref(): string {
		return '';
	}

	/**
	 * @return string
	 */
	public function getRequiredPermission(): string {
		return 'edit';
	}

	/**
	 * @return array
	 */
	public function getRLModules(): array {
		return [ 'ext.bluespice.bookshelf.editmetadata' ];
	}
}
