<?php

namespace BlueSpice\Bookshelf\BooksOverviewActions;

use BlueSpice\Bookshelf\IBooksOverviewAction;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;

class Export implements IBooksOverviewAction {

	/** @var Title */
	private $book = null;

	/** @var string */
	private $displayTitle = '';

	/**
	 *
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
		return 'book_pdf';
	}

	/**
	 * @return int
	 */
	public function getPosition(): int {
		return 20;
	}

	/**
	 * @return array
	 */
	public function getClasses(): array {
		return [ 'bs-books-overview-action-export' ];
	}

	/**
	 * @return array
	 */
	public function getIconClasses(): array {
		return [ 'bi-file-earmark-pdf-fill' ];
	}

	/**
	 * @return Message
	 */
	public function getText(): Message {
		return new Message( 'bs-bookshelf-books-overview-page-book-action-export-book-text' );
	}

	/**
	 * @return Message
	 */
	public function getTitle(): Message {
		$titleText = $this->book->getPrefixedText();
		if ( $this->displayTitle !== '' ) {
			$titleText = $this->displayTitle;
		}
		return new Message(
			'bs-bookshelf-books-overview-page-book-action-export-book-title',
			[ $titleText ]
		);
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
		return 'read';
	}

	/**
	 * @return array
	 */
	public function getRLModules(): array {
		return [ 'bs.bookshelf.action.export' ];
	}
}
