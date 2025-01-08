<?php

namespace BlueSpice\Bookshelf;

use Title;
use TitleFactory;
use Wikimedia\Rdbms\LoadBalancer;

class BookLookup {

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var LoadBalancer */
	private $loadBalancer = null;

	/** @var ChapterLookup */
	private $chapterLookup = null;

	/**
	 * @param TitleFactory $titleFactory
	 * @param LoadBalancer $loadBalancer
	 * @param ChapterLookup $chapterLookup
	 */
	public function __construct(
		TitleFactory $titleFactory, LoadBalancer $loadBalancer,	ChapterLookup $chapterLookup
	) {
		$this->titleFactory = $titleFactory;
		$this->loadBalancer = $loadBalancer;
		$this->chapterLookup = $chapterLookup;
	}

	/**
	 * @return BookDataModel[]
	 */
	public function getBooks(): array {
		$books = [];

		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );

		$results = $dbr->select(
			'bs_books',
			'*',
			[
				'book_type' => 'public'
			],
			__METHOD__
		);

		if ( $results->numRows() < 1 ) {
			return [];
		}

		foreach ( $results as $res ) {
			$book = $this->titleFactory->makeTitle( $res->book_namespace, $res->book_title );

			$key = $book->getPrefixedDBkey();
			if ( !isset( $books[$key] ) ) {
				$books[$key] = new BookDataModel(
					$res->book_namespace,
					$res->book_title,
					$res->book_name,
					$res->book_type
				);
			}
		}

		return $books;
	}

	/**
	 * @param Title $title
	 * @return BookDataModel[]
	 */
	public function getBooksForPage( Title $title ): array {
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );

		$res = $dbr->select(
			'bs_book_chapters',
			'chapter_book_id',
			[
				'chapter_namespace' => $title->getNamespace(),
				'chapter_title' => $title->getDBKey()
			],
			__METHOD__,
			[
				 'ORDER BY' => 'chapter_number'
			]
		);

		$bookIDs = [];
		foreach ( $res as $row ) {
			$bookIDs[] = $row->chapter_book_id;
		}

		if ( empty( $bookIDs ) ) {
			return [];
		}

		$res = $dbr->select(
			'bs_books',
			'*',
			[
				'book_id' => $bookIDs,
				'book_type' => 'public',
			],
			__METHOD__
		);

		if ( empty( $res ) ) {
			return [];
		}

		$books = [];
		foreach ( $res as $row ) {
			$book = $this->titleFactory->makeTitle( $row->book_namespace, $row->book_title );

			$key = $book->getPrefixedDBkey();
			if ( !isset( $books[$key] ) ) {
				$books[$key] = new BookDataModel(
					$row->book_namespace,
					$row->book_title,
					$row->book_name,
					$row->book_type
				);
			}
		}

		return $books;
	}

	/**
	 * @param Title $title
	 * @return int|null
	 */
	public function getBookId( Title $title ): ?int {
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );

		$result = $dbr->select(
			'bs_books',
			'book_id',
			[
				'book_namespace' => $title->getNamespace(),
				'book_title' => $title->getDBKey()
			],
			__METHOD__,
			[]
		);

		if ( !$result ) {
			return null;
		}

		$id = null;
		foreach ( $result as $res ) {
			$id = $res->book_id;
		}

		return $id;
	}

	/**
	 * @param Title $title
	 * @return BookInfo|null
	 */
	public function getBookInfo( Title $title ): ?BookInfo {
		return $this->bookInfoFromConds( [
			'book_namespace' => $title->getNamespace(),
			'book_title' => $title->getDBKey()
		] );
	}

	/**
	 * @param string $name
	 * @return BookInfo|null
	 */
	public function getBookInfoFromName( string $name ) {
		return $this->bookInfoFromConds( [
			'book_name' => $name
		] );
	}

	/**
	 * @param string $name
	 * @return Title|null
	 */
	public function getBookTitleFromName( string $name ): ?Title {
		$info = $this->getBookInfoFromName( $name );
		if ( !$info ) {
			return null;
		}
		return $this->titleFactory->makeTitle( $info->getNamespace(), $info->getTitle() );
	}

	/**
	 * @param array $conds
	 * @return BookInfo|null
	 */
	private function bookInfoFromConds( array $conds ): ?BookInfo {
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );

		$result = $dbr->selectRow(
			'bs_books',
			'*',
			$conds,
			__METHOD__,
			[]
		);

		if ( !$result ) {
			return null;
		}

		return new BookInfo(
			$result->book_id,
			$result->book_namespace,
			$result->book_title,
			$result->book_name,
			$result->book_type,
		);
	}

	/**
	 * @param Title $title
	 * @return array
	 */
	public function getBookHierarchy( Title $title ): array {
		$chapters = $this->chapterLookup->getChaptersOfBook( $title );
		$bookHierarchyBuilder = new BookHierarchyBuilder();
		$hierarchy = $bookHierarchyBuilder->build( $chapters );

		return $hierarchy;
	}
}
