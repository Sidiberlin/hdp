<?php

namespace BlueSpice\Bookshelf;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ObjectCacheFactory;
use Wikimedia\Rdbms\IConnectionProvider;

class BookLookup {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var IConnectionProvider */
	private $connectionProvider;

	/** @var ObjectCacheFactory */
	private $objectCacheFactory;

	/** @var ChapterLookup */
	private $chapterLookup;

	/**
	 * @param TitleFactory $titleFactory
	 * @param IConnectionProvider $connectionProvider
	 * @param ObjectCacheFactory $objectCacheFactory
	 * @param ChapterLookup $chapterLookup
	 */
	public function __construct(
		TitleFactory $titleFactory, IConnectionProvider $connectionProvider,
		ObjectCacheFactory $objectCacheFactory, ChapterLookup $chapterLookup
	) {
		$this->titleFactory = $titleFactory;
		$this->connectionProvider = $connectionProvider;
		$this->objectCacheFactory = $objectCacheFactory;
		$this->chapterLookup = $chapterLookup;
	}

	/**
	 * @return BookDataModel[]
	 */
	public function getBooks(): array {
		$books = [];

		$dbr = $this->connectionProvider->getReplicaDatabase();

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
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$objectCache = $this->objectCacheFactory->getLocalServerInstance();
		$fname = __METHOD__;

		$bookIDs = $objectCache->getWithSetCallback(
			$objectCache->makeKey( 'bluespicebookshelf-getbooksforpage', $title->getNamespace(), $title->getDBKey() ),
			$objectCache::TTL_PROC_SHORT,
			static function () use ( $title, $dbr, $fname ) {
				$res = $dbr->newSelectQueryBuilder()
					->table( 'bs_book_chapters' )
					->field( 'chapter_book_id' )
					->where( [
						'chapter_namespace' => (string)$title->getNamespace(),
						'chapter_title' => $title->getDBKey()
					] )
					->orderBy( 'chapter_number' )
					->caller( $fname )
					->fetchResultSet();

				$bookIDs = [];
				foreach ( $res as $row ) {
					$bookIDs[] = $row->chapter_book_id;
				}

				return $bookIDs;
			}
		);

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
		$dbr = $this->connectionProvider->getReplicaDatabase();

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
	 * @param int $id
	 * @return Title|null
	 */
	public function getBookTitleFromID( $id ): ?Title {
		$dbr = $this->connectionProvider->getReplicaDatabase();

		$result = $dbr->select(
			'bs_books',
			'*',
			[
				'book_id' => $id
			],
			__METHOD__,
			[]
		);

		if ( !$result ) {
			return null;
		}

		$title = null;
		foreach ( $result as $res ) {
			$title = $this->titleFactory->newFromText( $res->book_title, $res->book_namespace );
		}

		return $title;
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
		$dbr = $this->connectionProvider->getReplicaDatabase();

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
