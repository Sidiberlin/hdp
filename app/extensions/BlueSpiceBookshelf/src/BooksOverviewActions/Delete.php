<?php

namespace BlueSpice\Bookshelf\BooksOverviewActions;

use BlueSpice\Bookshelf\IBooksOverviewAction;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;

class Delete implements IBooksOverviewAction {

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
		return 'book_delete';
	}

	/**
	 * @return int
	 */
	public function getPosition(): int {
		return 40;
	}

	/**
	 * @return array
	 */
	public function getClasses(): array {
		return [ 'bs-books-overview-action-delete' ];
	}

	/**
	 * @return array
	 */
	public function getIconClasses(): array {
		return [ 'bi-trash' ];
	}

	/**
	 * @return Message
	 */
	public function getText(): Message {
		return new Message( 'bs-books-overview-page-book-action-delete-text' );
	}

	/**
	 * @return Message
	 */
	public function getTitle(): Message {
		$titleText = $this->book->getPrefixedText();
		if ( $this->displayTitle !== '' ) {
			$titleText = $this->displayTitle;
		}
		return new Message( 'bs-books-overview-page-book-action-delete-title', [ $titleText ] );
	}

	/**
	 * @return string
	 */
	public function getHref(): string {
		return $this->book->getLocalURL( 'action=delete' );
	}

	/**
	 * @return string
	 */
	public function getRequiredPermission(): string {
		return 'delete';
	}

	/**
	 * @return array
	 */
	public function getRLModules(): array {
		return [];
	}
}
