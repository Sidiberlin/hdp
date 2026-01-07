<?php

namespace BlueSpice\Bookshelf\ContextProvider;

use BlueSpice\Bookshelf\IBookContextProvider;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;

class DefaultProvider implements IBookContextProvider {

	/** @var array */
	private $books = [];

	/** @var TitleFactory */
	private $titleFactory = null;

	/**
	 * @param array $books
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( array $books, TitleFactory $titleFactory ) {
		$this->books = $books;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @return bool
	 */
	public function isResponsible(): bool {
		return true;
	}

	/**
	 * Returns the book title that should be used
	 * for the current page
	 *
	 * @return ?Title
	 */
	public function getActiveBook(): ?Title {
		if ( empty( $this->books ) ) {
			return null;
		}

		/** @var BookDataModel */
		$bookData = array_shift( $this->books );
		$book = $this->titleFactory->makeTitle(
			$bookData->getNamespace(),
			$bookData->getTitle()
		);

		return $book;
	}

	/**
	 * @return int
	 */
	public function getPriority(): int {
		return 10;
	}

}
