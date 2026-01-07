<?php

namespace BlueSpice\Bookshelf;

use HtmlArmor;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;

class TreeDataProvider {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var Title */
	private $title;

	/** @var BookContextProviderFactory */
	private $bookContextProviderFactory = null;

	/** @var BookLookup */
	private $bookLookup = null;

	/** @var string */
	private $idPrefix = null;

	/**
	 * @param BookLookup $bookLookup
	 * @param BookContextProviderFactory $bookContextProviderFactory
	 * @param TitleFactory $titleFactory
	 * @param string $idPrefix
	 */
	public function __construct(
		BookLookup $bookLookup, BookContextProviderFactory $bookContextProviderFactory,
		TitleFactory $titleFactory, string $idPrefix = ''
	) {
		$this->bookLookup = $bookLookup;
		$this->bookContextProviderFactory = $bookContextProviderFactory;
		$this->titleFactory = $titleFactory;
		$this->idPrefix = $idPrefix;
	}

	/**
	 * @param Title $title
	 * @param Title|null $forceActiveBook
	 * @return array
	 */
	public function get( Title $title, $forceActiveBook = null ): array {
		$this->title = $title;
		if ( $forceActiveBook === null ) {
			$books = $this->bookLookup->getBooksForPage( $title );
			if ( empty( $books ) ) {
				return [];
			}
			$bookContextProvider = $this->bookContextProviderFactory->getProvider( $title );
			$activeBook = $bookContextProvider->getActiveBook();
		} else {
			$activeBook = $forceActiveBook;
		}

		if ( !$activeBook ) {
			return [];
		}
		$items = $this->bookLookup->getBookHierarchy( $activeBook );

		$data = [];
		foreach ( $items as $item ) {
			$this->processItem( $activeBook, $item, $data );
		}

		return $data;
	}

	/**
	 * @param Title $activeBook
	 * @param array $item
	 * @param array &$data
	 * @param string $path
	 * @return void
	 */
	private function processItem( Title $activeBook, array $item, array &$data, $path = '' ): void {
		$itemData = [];

		$book = $activeBook->getPrefixedDBkey();

		/**
		 * Chapter number necessary to keep book hierarchy consistent even
		 * if same pages are multiple times in one book
		 * ERM37464
		 */
		$fullId = md5( $book . $item['chapter_name'] . $item['chapter_number'] );
		$id = $this->idPrefix;
		$id .= substr( $fullId, 0, 6 );
		$path .= "/$id";

		$title = $this->titleFactory->makeTitle(
			$item['chapter_namespace'],
			$item['chapter_title']
		);

		if ( $this->getNodeType( $item ) === 'plain-text' ) {
			$itemData = $this->makeTextNode( $item, $title, $id, $path );
		}
		if ( $this->getNodeType( $item ) === 'wikilink-with-alias' ) {
			$itemData = $this->makeLinkNode( $activeBook, $item, $title, $id, $path );
		}
		if ( $this->getNodeType( $item ) === 'wikilink' ) {
			$itemData = $this->makeLinkNode( $activeBook, $item, $title, $id, $path );
		}

		if ( isset( $item['chapter_children'] ) && !empty( $item['chapter_children'] ) ) {
			foreach ( $item['chapter_children'] as $children ) {
				$this->processItem( $activeBook, $children, $itemData['items'], $path );
			}
		}

		if ( !empty( $itemData ) ) {
			$data[] = $itemData;
		}
	}

	/**
	 * @param array $item
	 * @return string
	 */
	private function getNodeType( array $item ): string {
		return $item['chapter_type'];
	}

	/**
	 * @param array $item
	 * @param Title $title
	 * @param string $id
	 * @param string $path
	 * @return array
	 */
	private function makeTextNode( array $item, Title $title, string $id, string $path ): array {
		$data = [
			'id' => $id,
			'type' => $item['chapter_type'],
			'namespace' => $item['chapter_namespace'],
			'title' => $item['chapter_title'],
			'name' => $item['chapter_name'],
			'text' => $item['chapter_name'],
			'preHtml' => new HtmlArmor( '<span class="bs-chapter-number">' . $item['chapter_number'] . '</span> ' ),
			'path' => trim( $path, '/' ),
			'items' => []
		];

		$classes = $this->getClasses( $title, [ $item['chapter_type'] ] );

		if ( !empty( $classes ) ) {
			$data['classes'] = $classes;
		}

		return $data;
	}

	/**
	 * @param Title $activeBook
	 * @param array $item
	 * @param Title $title
	 * @param string $id
	 * @param string $path
	 * @return array
	 */
	private function makeLinkNode( Title $activeBook, array $item, Title $title, $id, $path ): array {
		$data = $this->makeTextNode( $item, $title, $id, $path );
		$text = $activeBook->getPrefixedText();
		$data['href'] = $title->getLocalURL( "book=$text" );

		return $data;
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	private function isActiveTitle( Title $title ): bool {
		if ( $title->equals( $this->title ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @param Title $title
	 * @param array $classes
	 * @return array
	 */
	private function getClasses( Title $title, array $classes = [] ): array {
		if ( $this->isActiveTitle( $title ) ) {
			$classes[] = 'acitve';
			$classes[] = 'active';
		}

		return $classes;
	}

}
