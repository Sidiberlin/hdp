<?php

namespace BlueSpice\Bookshelf\BooksOverviewActions;

use BlueSpice\Bookshelf\IBooksOverviewAction;
use Message;
use Title;

class Edit implements IBooksOverviewAction {

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
		return 'book_edit';
	}

	/**
	 * @return int
	 */
	public function getPosition(): int {
		return 10;
	}

	/**
	 * @return array
	 */
	public function getClasses(): array {
		return [ 'bs-books-overview-action-edit' ];
	}

	/**
	 * @return array
	 */
	public function getIconClasses(): array {
		return [ 'icon-edit' ];
	}

	/**
	 * @return Message
	 */
	public function getText(): Message {
		return new Message( 'bs-books-overview-page-book-action-edit-text' );
	}

	/**
	 * @return Message
	 */
	public function getTitle(): Message {
		$titleText = $this->book->getPrefixedText();
		if ( $this->displayTitle !== '' ) {
			$titleText = $this->displayTitle;
		}
		return new Message( 'bs-books-overview-page-book-action-edit-title', [ $titleText ] );
	}

	/**
	 * @return string
	 */
	public function getHref(): string {
		return $this->book->getEditURL();
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
		return [];
	}
}
