<?php

namespace BlueSpice\Bookshelf\Panel;

use BlueSpice\Bookshelf\BookLookup;
use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleDropdown;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleDropdownItemlistFromArray;

class BookSelectWidget extends SimpleDropdown {

	/** @var Title */
	private $activeBook = null;

	/** @var Title */
	private $title;

	/** @var BookLookup */
	private $bookLookup = null;

	/** @var TitleFactory */
	private $titleFactory;

	/**
	 *
	 * @param array $options
	 * @param Title $activeBook
	 * @param Title $title
	 * @param BookLookup $bookLookup
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( $options, Title $activeBook, Title $title,
		BookLookup $bookLookup, TitleFactory $titleFactory
	) {
		parent::__construct( $options );

		$this->activeBook = $activeBook;
		$this->title = $title;
		$this->bookLookup = $bookLookup;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @return Message
	 */
	public function getText(): Message {
		return new RawMessage( $this->getBookTitle( $this->activeBook ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getSubComponents(): array {
		$links = [];
		$books = $this->getBooks();
		foreach ( $books as $book ) {
			$bookTitle = $this->titleFactory->makeTitle( $book->getNamespace(), $book->getTitle() );
			$text = $bookTitle->getFullText();
			$href = $this->title->getLocalURL( "book=$text" );
			$links[] = [
				'href' => $href,
				'text' => $book->getName()
			];
		}
		return [
			new SimpleDropdownItemlistFromArray( [
				'id' => 'book-selector-list',
				'links' => $links
			] )
		];
	}

	/**
	 * @return Message
	 */
	public function getTitle(): Message {
		return $this->getText();
	}

	/**
	 * @return Message
	 */
	public function getAriaLabel(): Message {
		return $this->getText();
	}

	/**
	 * @return array
	 */
	public function getContainerClasses(): array {
		return [ 'book-select-widget' ];
	}

	/**
	 * @return array
	 */
	public function getMenuClasses(): array {
		return [ 'mws-dropdown-secondary' ];
	}

	/**
	 * @param Title|null $activeBook
	 * @return string
	 */
	private function getBookTitle( $activeBook ): string {
		if ( $activeBook instanceof Title ) {
			return $activeBook->getText();
		}

		return '';
	}

	private function getBooks(): array {
		$books = $this->bookLookup->getBooksForPage( $this->title );
		return $books;
	}
}
