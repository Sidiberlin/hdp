<?php

namespace BlueSpice\Bookshelf;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LoadBalancer;

class BookUpdater {

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var LoadBalancer */
	private $loadBalancer = null;

	/** @var BookLookup */
	private $bookLookup = null;

	/**
	 * @param TitleFactory $titleFactory
	 * @param LoadBalancer $loadBalancer
	 * @param BookLookup $bookLookup
	 */
	public function __construct( TitleFactory $titleFactory, LoadBalancer $loadBalancer, BookLookup $bookLookup ) {
		$this->loadBalancer = $loadBalancer;
		$this->bookLookup = $bookLookup;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @param Title $book
	 * @return bool
	 */
	public function delete( Title $book ): bool {
		$bookId = $this->bookLookup->getBookId( $book );
		if ( $bookId === null ) {
			return false;
		}

		/** @var IDatabase|false */
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );

		// Delete 'bs_book_chapters' table entries
		$status = $dbw->delete(
			'bs_book_chapters',
			[
				'book_id' => $bookId
			],
			__METHOD__
		);

		// Deelet 'bs_books' talbe entries
		$status = $dbw->delete(
			'bs_books',
			[
				'book_id' => $bookId
			],
			__METHOD__
		);

		return $status;
	}

	/**
	 * @param BookDataModel $bookData
	 * @return bool
	 */
	private function insert( BookDataModel $bookData ): bool {
		/** @var IDatabase|false */
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		$res = $dbw->insert(
			'bs_books',
			[
				'book_namespace' => $bookData->getNamespace(),
				'book_title' => $bookData->getTitle(),
				'book_name' => $bookData->getName(),
				'book_type' => $bookData->getType(),
			],
			__METHOD__
		);

		if ( !$res ) {
			return false;
		}

		return true;
	}

	/**
	 * @param BookDataModel $bookData
	 * @return bool
	 */
	public function update( BookDataModel $bookData ): bool {
		$book = $this->titleFactory->makeTitle( $bookData->getNamespace(), $bookData->getTitle() );

		$status = $this->delete( $book );
		if ( !$status ) {
			return false;
		}

		$status = $this->insert( $bookData );
		return $status;
	}
}
