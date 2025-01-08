<?php

namespace BlueSpice\Bookshelf;

use ConfigFactory;
use stdClass;
use Title;
use TitleFactory;
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

	/**
	 * @param LoadBalancer $loadBalancer
	 * @param TitleFactory $titleFactory
	 * @param ConfigFactory $configFactory
	 */
	public function __construct(
		LoadBalancer $loadBalancer, TitleFactory $titleFactory, ConfigFactory $configFactory
	) {
		$this->loadBalancer = $loadBalancer;
		$this->titleFactory = $titleFactory;
		$this->config = $configFactory->makeConfig( 'bsg' );
	}

	/**
	 * @param Title $title
	 * @return array
	 */
	public function getChaptersOfBook( Title $title ): array {
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
		);

		foreach ( $results as $result ) {
			$pages[] = $this->makeChapter( $result, $db );
		}

		return $pages;
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
			]
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
			]
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
			]
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
				'chapter_number like "' . $chapterInfo->getNumber() . '%"',
				'NOT chapter_number="' . $chapterInfo->getNumber() . '"',
			]
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

			// Check if page property displaytitle is set
			$name = $this->makeName( $title, $title->getText(), $db );

			if ( $this->config->get( 'BookshelfTitleDisplayText' )
				&& $result->chapter_name !== $title->getSubpageText()
			) {
				// reset to database value
				$name = $result->chapter_name;
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

			// Check if page property displaytitle is set
			$name = $this->makeName( $title, $title->getText(), $db );

			if ( $this->config->get( 'BookshelfTitleDisplayText' )
				&& $result->chapter_name !== $title->getText()
			) {
				// reset to database value
				$name = $result->chapter_name;
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
	 * @param IDatabase $db
	 * @return string
	 */
	private function makeName( Title $title, string $name, IDatabase $db ): string {
		$res = $db->select(
			'page_props',
			[ '*' ],
			[
				'pp_page' => $title->getId(),
				'pp_propname' => 'displaytitle'
			]
		);

		foreach ( $res as $row ) {
			$name = $row->pp_value;
		}
		return $name;
	}
}
