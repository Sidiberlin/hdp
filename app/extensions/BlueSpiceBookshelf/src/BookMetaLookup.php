<?php

namespace BlueSpice\Bookshelf;

use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LoadBalancer;

class BookMetaLookup {

	/**
	 * @var LoadBalancer
	 */
	private $loadBalancer = null;

	/** @var BookLookup */
	private $bookLookup = null;

	/** @var IDatabase */
	private $db = null;

	/**
	 * @param LoadBalancer $loadBalancer
	 * @param BookLookup $bookLookup
	 */
	public function __construct( LoadBalancer $loadBalancer, BookLookup $bookLookup	) {
		$this->loadBalancer = $loadBalancer;
		$this->bookLookup = $bookLookup;
		$this->db = $this->loadBalancer->getConnection( DB_REPLICA );
	}

	/**
	 * @param Title $book
	 * @return array
	 */
	public function getMetaForBook( Title $book ): array {
		$meta = [];

		$bookID = $this->bookLookup->getBookId( $book );

		$results = $this->db->select(
			'bs_book_meta',
			'*',
			[
				'm_book_id' => $bookID
			],
			__METHOD__
		);

		foreach ( $results as $result ) {
			$key = $result->m_key;
			$meta[$key] = $result->m_value;
		}

		return $meta;
	}

	/**
	 * @param Title $book
	 * @param string $key
	 * @return string
	 */
	public function getMetaValueForBook( Title $book, string $key ): string {
		$value = '';

		$bookID = $this->bookLookup->getBookId( $book );

		$results = $this->db->select(
			'bs_book_meta',
			'm_value',
			[
				'm_book_id' => $bookID,
				'm_key' => $key
			],
			__METHOD__
		);

		foreach ( $results as $result ) {
			$value = $result->m_value;
		}

		return $value;
	}

	/**
	 * @param string $key
	 * @return array
	 */
	public function getAllMetaValuesForKey( string $key ): array {
		$values = [];

		$results = $this->db->select(
			'bs_book_meta',
			'm_value',
			[
				'm_key' => $key
			],
			__METHOD__
		);

		foreach ( $results as $result ) {
			$values[] = $result->m_value;
		}

		array_unique( $values );

		return $values;
	}
}
