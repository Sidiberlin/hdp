<?php

namespace BlueSpice\Bookshelf;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class ChapterUpdater {

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var BookLookup */
	private $bookLookup;

	/** @var HookContainer */
	private $hookContainer;

	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param BookLookup $bookLookup
	 * @param HookContainer $hookContainer
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		ILoadBalancer $loadBalancer, BookLookup $bookLookup, HookContainer $hookContainer, TitleFactory $titleFactory
	) {
		$this->loadBalancer = $loadBalancer;
		$this->bookLookup = $bookLookup;
		$this->hookContainer = $hookContainer;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @param Title $book
	 * @param bool $internal
	 * @return bool
	 */
	public function delete( Title $book, bool $internal = false ): bool {
		$bookId = $this->bookLookup->getBookId( $book );
		if ( $bookId === null ) {
			return false;
		}

		/** @var IDatabase|false */
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );

		if ( !$internal ) {
			$this->emitDeletion( $book, $this->getRawChapterTitles( $book ) );
		}
		// Remove old entries
		return $dbw->delete(
			'bs_book_chapters',
			[
				'chapter_book_id' => $bookId
			],
			__METHOD__
		);
	}

	/**
	 * @param Title $book
	 * @param ChapterDataModel[] $chapters
	 * @return bool
	 */
	private function insert( Title $book, array $chapters ): bool {
		$bookId = $this->bookLookup->getBookId( $book );
		if ( $bookId === null ) {
			return false;
		}
		/** @var IDatabase|false */
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );

		foreach ( $chapters as $chapter ) {
			$res = $dbw->insert(
				'bs_book_chapters',
				[
					'chapter_namespace' => $chapter->getNamespace(),
					'chapter_title' => $chapter->getTitle(),
					'chapter_name' => $chapter->getName(),
					'chapter_number' => $chapter->getNumber(),
					'chapter_type' => $chapter->getType(),
					'chapter_book_id' => $bookId
				],
				__METHOD__
			);

			if ( !$res ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param Title $book
	 * @param ChapterDataModel[] $chapters
	 * @return bool
	 */
	public function update( Title $book, array $chapters ): bool {
		$diff = $this->getChapterDiff( $book, $chapters );
		$status = $this->delete( $book, true );
		if ( !$status ) {
			return false;
		}

		$res = $this->insert( $book, $chapters );
		if ( $res ) {
			$this->emitInsertion( $book, $diff['added'] );
			$this->emitDeletion( $book, $diff['removed'] );
		}
		return $res;
	}

	/**
	 * Get raw namespace/title pairs for all pages in a book
	 * @param Title $book
	 * @return array
	 */
	private function getRawChapterTitles( Title $book ): array {
		$bookId = $this->bookLookup->getBookId( $book );
		if ( $bookId === null ) {
			return [];
		}
		$res = $this->loadBalancer->getConnection( DB_REPLICA )->select(
			'bs_book_chapters',
			[
				'chapter_title',
				'chapter_namespace'
			],
			[
				'chapter_book_id' => $bookId,
				'chapter_namespace IS NOT NULL'
			],
			__METHOD__
		);

		$chapters = [];
		foreach ( $res as $row ) {
			if ( !$row->chapter_title ) {
				continue;
			}
			$chapters[] = [
				'title' => $row->chapter_title,
				'namespace' => $row->chapter_namespace
			];
		}

		return $chapters;
	}

	/**
	 * @param Title $book
	 * @param ChapterDataModel[] $chapters
	 * @return array
	 */
	private function getChapterDiff( Title $book, array $chapters ): array {
		$diff = [ 'added' => [], 'removed' => [] ];
		$rawChapters = $this->getRawChapterTitles( $book );
		$rawChapters = $this->serializeChapters( $rawChapters );
		$newChapters = $this->serializeChapters( array_map( static function ( ChapterDataModel $chapter ) {
			return [
				'title' => $chapter->getTitle(),
				'namespace' => $chapter->getNamespace()
			];
		}, $chapters ) );

		$added = array_diff_key( $newChapters, $rawChapters );
		$removed = array_diff_key( $rawChapters, $newChapters );
		foreach ( $added as $chapter ) {
			$diff['added'][] = $chapter;
		}
		foreach ( $removed as $chapter ) {
			$diff['removed'][] = $chapter;
		}

		return $diff;
	}

	/**
	 * @param array $chapters
	 * @return array
	 */
	private function serializeChapters( array $chapters ): array {
		$res = [];
		foreach ( $chapters as $chapter ) {
			if ( !$chapter['title'] ) {
				continue;
			}
			$res[md5( $chapter['namespace'] . $chapter['title'] )] = $chapter;
		}
		return $res;
	}

	/**
	 * @param Title $book
	 * @param ChapterDataModel[] $chapters
	 * @return void
	 */
	private function emitInsertion( Title $book, array $chapters ) {
		$this->emitChange( 'BSBookshelfPageAddedToBook', $book, $chapters );
	}

	/**
	 * @param Title $book
	 * @param array $chapters
	 * @return void
	 */
	private function emitDeletion( Title $book, array $chapters ) {
		$this->emitChange( 'BSBookshelfPageRemovedFromBook', $book, $chapters );
	}

	/**
	 * @param string $hook
	 * @param Title $book
	 * @param array $chapters
	 * @return void
	 */
	private function emitChange( string $hook, Title $book, array $chapters ) {
		foreach ( $chapters as $chapter ) {
			$title = $this->titleFactory->makeTitle( $chapter['namespace'], $chapter['title'] );
			$this->hookContainer->run( $hook, [ $book, $title ] );
		}
	}
}
