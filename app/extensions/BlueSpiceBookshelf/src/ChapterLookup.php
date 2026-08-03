<?php

namespace BlueSpice\Bookshelf;

use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\Utils\DisplayTitleHelper;
use MWStake\MediaWiki\Component\Utils\UtilityFactory;
use stdClass;
use WANObjectCache;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LoadBalancer;

class ChapterLookup {

	/**
	 * @var LoadBalancer
	 */
	private $loadBalancer = null;

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var Config */
	private $config = null;

	/** @var DisplayTitleHelper|DisplayTitleHelper */
	private DisplayTitleHelper $displayTitleHelper;

	/** @var WANObjectCache */
	private $wanCache = null;

	/**
	 * @param LoadBalancer $loadBalancer
	 * @param TitleFactory $titleFactory
	 * @param ConfigFactory $configFactory
	 * @param UtilityFactory $utilityFactory
	 * @param WANObjectCache $wanCache
	 */
	public function __construct(
		LoadBalancer $loadBalancer, TitleFactory $titleFactory,
		ConfigFactory $configFactory, UtilityFactory $utilityFactory,
		WANObjectCache $wanCache
	) {
		$this->loadBalancer = $loadBalancer;
		$this->titleFactory = $titleFactory;
		$this->config = $configFactory->makeConfig( 'bsg' );
		$this->displayTitleHelper = $utilityFactory->getDisplayTitleHelper();
		$this->wanCache = $wanCache;
	}

	/**
	 * @param Title $title
	 * @return array
	 */
	public function getChaptersOfBook( Title $title ): array {
		$cacheKey = $this->wanCache->makeGlobalKey(
			'bsBookshelf', 'chapters',
			(string)$title->getNamespace(), $title->getDBKey()
		);
		$checkKey = $this->makeChaptersCheckKey( $title );

		return $this->wanCache->getWithSetCallback(
			$cacheKey,
			WANObjectCache::TTL_DAY,
			function () use ( $title ) {
				return $this->doGetChaptersOfBook( $title );
			},
			[ 'checkKeys' => [ $checkKey ] ]
		);
	}

	/**
	 * Invalidate the chapters cache for a book page. Call this whenever the NS_BOOK
	 * page is saved, deleted, or moved so the next request repopulates the cache.
	 *
	 * @param Title $book
	 */
	public function invalidateChaptersOfBookCache( Title $book ): void {
		$this->wanCache->touchCheckKey( $this->makeChaptersCheckKey( $book ) );
	}

	/**
	 * @param Title $book
	 * @return string
	 */
	private function makeChaptersCheckKey( Title $book ): string {
		return $this->wanCache->makeGlobalKey(
			'bsBookshelf', 'chapters-check',
			(string)$book->getNamespace(), $book->getDBKey()
		);
	}

	/**
	 * @param Title $title
	 * @return array
	 */
	private function doGetChaptersOfBook( Title $title ): array {
		$pages = [];

		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$res = $db->select(
			'bs_books',
			'book_id',
			[
				'book_namespace' => $title->getNamespace(),
				'book_title' => $title->getDBKey()
			],
			__METHOD__
		);

		if ( $res->numRows() === 0 ) {
			return [];
		}

		$bookID = null;
		foreach ( $res as $row ) {
			$bookID = $row->book_id;
		}

		$results = $db->select(
			'bs_book_chapters',
			'*',
			[
				'chapter_book_id' => $bookID
			],
			__METHOD__
		);

		foreach ( $results as $result ) {
			$pages[] = $this->makeChapter( $result, $db );
		}

		return $pages;
	}

	/**
	 * Get first valid chapter title for a book, ignoring chapters with invalid titles
	 *
	 * @param Title $bookTitle
	 * @param array $ignoreChapters
	 *
	 * @return Title|null if no valid chapter title is found
	 */
	public function getFirstChapterTitle( Title $bookTitle, array $ignoreChapters = [] ): ?Title {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$query = $db->newSelectQueryBuilder()
			->select( [ 'chapter_id', 'chapter_namespace', 'chapter_title' ] )
			->from( 'bs_books', 'b' )
			->from( 'bs_book_chapters', 'bc' )
			->join( 'bs_book_chapters', 'bc', [ 'b.book_id = bc.chapter_book_id' ] )
			->where( [
				'b.book_namespace' => $bookTitle->getNamespace(),
				'b.book_title' => $bookTitle->getDBKey(),
				'bc.chapter_namespace IS NOT NULL'
			] )
			->orderBy( [ 'bc.chapter_number' ], 'ASC' )
			->limit( 1 );

		if ( !empty( $ignoreChapters ) ) {
			// Mechanism to retry query if chapter title is invalid, ignoring previously found invalid chapter ids
			$query->where( 'bc.chapter_id NOT IN (' . $db->makeList( $ignoreChapters ) . ')' );
		}
		$res = $query->fetchRow();

		if ( !$res ) {
			return null;
		}
		$title = $this->titleFactory->makeTitleSafe(
			$res->chapter_namespace,
			$res->chapter_title
		);
		if ( !$title ) {
			return $this->getFirstChapterTitle( $bookTitle, array_merge( $ignoreChapters, [ $res->chapter_id ] ) );
		}
		return $title;
	}

	/**
	 * @param Title $bookTitle
	 * @return int
	 */
	public function countChapters( Title $bookTitle ): int {
		$count = $this->loadBalancer->getConnection( DB_REPLICA )->newSelectQueryBuilder()
			->select( [ 'COUNT(*) as chapter_count' ] )
			->table( 'bs_books', 'b' )
			->table( 'bs_book_chapters', 'bc' )
			->where( [
				'b.book_namespace' => $bookTitle->getNamespace(),
				'b.book_title' => $bookTitle->getDBKey(),
			] )
			->join( 'bs_book_chapters', 'bc', [ 'b.book_id = bc.chapter_book_id' ] )
			->fetchField();
		if ( !$count ) {
			return 0;
		}
		return (int)$count;
	}

	/**
	 * @param Title $book
	 * @param Title $title
	 * @return ChapterInfo|null
	 */
	public function getChapterInfoFor( Title $book, Title $title ): ?ChapterInfo {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$bookID = $db->selectField(
			'bs_books',
			'book_id',
			[
				'book_namespace' => $book->getNamespace(),
				'book_title' => $book->getDBKey(),
			],
			__METHOD__
		);

		if ( $bookID === null ) {
			return null;
		}

		$results = $db->select(
			'bs_book_chapters',
			'*',
			[
				'chapter_book_id' => $bookID,
				'chapter_namespace' => $title->getNamespace(),
				'chapter_title' => $title->getDBKey(),
			],
			__METHOD__
		);

		$chapterInfo = null;
		foreach ( $results as $result ) {
			$chapterInfo = $this->makeChapterInfo( $result, $db );
		}

		return $chapterInfo;
	}

	/**
	 * @param int $bookID
	 * @param string $chapterNumber
	 * @return ChapterInfo|null
	 */
	public function getChapterInfoForNumber( int $bookID, string $chapterNumber ): ?ChapterInfo {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );

		$results = $db->select(
			'bs_book_chapters',
			'*',
			[
				'chapter_book_id' => $bookID,
				'chapter_number' => $chapterNumber,
			],
			__METHOD__
		);

		$chapterInfo = null;
		foreach ( $results as $result ) {
			$chapterInfo = $this->makeChapterInfo( $result, $db );
		}

		return $chapterInfo;
	}

	/**
	 * @param Title $book
	 * @param ChapterInfo $chapterInfo
	 * @return ChapterInfo[]
	 */
	public function getChildren( Title $book, ChapterInfo $chapterInfo ): array {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$res = $db->select(
			'bs_books',
			'book_id',
			[
				'book_namespace' => $book->getNamespace(),
				'book_title' => $book->getDBKey(),
			],
			__METHOD__
		);

		$bookID = null;
		foreach ( $res as $row ) {
			$bookID = $row->book_id;
		}

		if ( $bookID === null ) {
			return [];
		}

		$results = $db->select(
			'bs_book_chapters',
			'*',
			[
				'chapter_book_id=' . $bookID,
				'chapter_number LIKE "' . $chapterInfo->getNumber() . '.%"',
				'chapter_number NOT LIKE "' . $chapterInfo->getNumber() . '.%.%"'
			],
			__METHOD__
		);

		$children = [];
		foreach ( $results as $result ) {
			$children[] = $this->makeChapter( $result, $db );
		}

		return $children;
	}

	/**
	 * @param stdClass $result
	 * @param IDatabase $db
	 * @return ChapterInfo
	 */
	private function makeChapterInfo( stdClass $result, IDatabase $db ): ChapterInfo {
		$name = $result->chapter_name;

		if ( $result->chapter_namespace !== null && $result->chapter_title !== null ) {
			$title = $this->titleFactory->makeTitle(
				$result->chapter_namespace,
				$result->chapter_title
			);

			if ( $title->canExist() ) {
				// Check if page property displaytitle is set
				$name = $this->makeName( $title, $title->getText() );

				if ( $this->config->get( 'BookshelfTitleDisplayText' )
					&& $result->chapter_name !== $title->getSubpageText()
				) {
					// reset to database value
					$name = $result->chapter_name;
				}
			}
		}

		return new ChapterInfo(
			$name,
			$result->chapter_number,
			$result->chapter_type
		);
	}

	/**
	 * @param stdClass $result
	 * @param IDatabase $db
	 * @return ChapterDataModel
	 */
	private function makeChapter( stdClass $result, IDatabase $db ): ChapterDataModel {
		$name = $result->chapter_name;

		if ( $result->chapter_namespace !== null && $result->chapter_title !== null ) {
			$title = $this->titleFactory->makeTitle(
				$result->chapter_namespace,
				$result->chapter_title
			);

			if ( $title->canExist() ) {
				// Check if page property displaytitle is set
				$name = $this->makeName( $title, $title->getText(), $db );

				if ( $this->config->get( 'BookshelfTitleDisplayText' )
					&& $result->chapter_name !== $title->getText()
				) {
					// reset to database value
					$name = $result->chapter_name;
				}
			}
		}

		$number = (string)$result->chapter_number;
		$normalizedNumber = trim( $number, '.' );

		return new ChapterDataModel(
			$result->chapter_namespace,
			$result->chapter_title,
			$name,
			$normalizedNumber,
			$result->chapter_type,
		);
	}

	/**
	 * @param Title $title
	 * @param string $name
	 * @return string
	 */
	private function makeName( Title $title, string $name ): string {
		$display = $this->displayTitleHelper->getDisplayTitle( $title );
		return $display ?? $name;
	}

	/**
	 * @param int $bookId
	 * @return ChapterDataModel[]
	 */
	public function getFirstChapterForBookId( $bookId ) {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$results = $db->select(
			'bs_book_chapters',
			'*',
			[
				'chapter_book_id' => $bookId,
				'chapter_number NOT LIKE \'%.%\''
			],
			__METHOD__
		);

		foreach ( $results as $result ) {
			$pages[] = $this->makeChapter( $result, $db );
		}

		return $pages;
	}

	/**
	 * @param int $bookId
	 * @param ChapterDataModel $chapterInfo
	 * @return array
	 */
	public function getChapterChildrenForBookId( $bookId, $chapterInfo ) {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$results = $db->select(
			'bs_book_chapters',
			'*',
			[
				'chapter_book_id=' . $bookId,
				'chapter_number LIKE \'' . $chapterInfo->getNumber() . '.%\'',
				'chapter_number NOT LIKE \'' . $chapterInfo->getNumber() . '.%.%\''
			],
			__METHOD__
		);

		$children = [];
		foreach ( $results as $result ) {
			$children[] = $this->makeChapter( $result, $db );
		}

		return $children;
	}
}
