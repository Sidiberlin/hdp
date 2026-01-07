<?php

namespace BlueSpice\Bookshelf\BooksOverviewActions;

use BlueSpice\Bookshelf\IBooksOverviewAction;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;

class View implements IBooksOverviewAction {

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
		return 'book_view';
	}

	/**
	 * @return int
	 */
	public function getPosition(): int {
		return 30;
	}

	/**
	 * @return array
	 */
	public function getClasses(): array {
		return [ 'bs-books-overview-action-view' ];
	}

	/**
	 * @return array
	 */
	public function getIconClasses(): array {
		return [ 'bi-file-earmark' ];
	}

	/**
	 * @return Message
	 */
	public function getText(): Message {
		return new Message( 'bs-books-overview-page-book-action-view-text' );
	}

	/**
	 * @return Message
	 */
	public function getTitle(): Message {
		$titleText = $this->book->getPrefixedText();
		if ( $this->displayTitle !== '' ) {
			$titleText = $this->displayTitle;
		}
		return new Message( 'bs-books-overview-page-book-action-view-title', [ $titleText ] );
	}

	/**
	 * @return string
	 */
	public function getHref(): string {
		return $this->book->getLocalUrl();
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
		return [];
	}
}
