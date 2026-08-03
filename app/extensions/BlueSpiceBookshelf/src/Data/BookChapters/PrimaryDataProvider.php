<?php

namespace BlueSpice\Bookshelf\Data\BookChapters;

use MWStake\MediaWiki\Component\DataStore\Filter;
use MWStake\MediaWiki\Component\DataStore\FilterFinder;
use MWStake\MediaWiki\Component\DataStore\PrimaryDatabaseDataProvider;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use MWStake\MediaWiki\Component\DataStore\Schema;
use stdClass;
use WANObjectCache;
use Wikimedia\Rdbms\IDatabase;

class PrimaryDataProvider extends PrimaryDatabaseDataProvider {

	/** @var WANObjectCache */
	private $wanCache;

	/**
	 * @param IDatabase $db
	 * @param Schema $schema
	 * @param WANObjectCache $wanCache
	 */
	public function __construct( IDatabase $db, Schema $schema, WANObjectCache $wanCache ) {
		parent::__construct( $db, $schema );
		$this->wanCache = $wanCache;
	}

	/**
	 * @return string[]
	 */
	protected function getTableNames() {
		return [ 'bs_book_chapters' ];
	}

	/**
	 * @param ReaderParams $params
	 * @return Record[]
	 */
	public function makeData( $params ) {
		$filterFinder = new FilterFinder( $params->getFilter() );
		$bookIdFilter = $filterFinder->findByField( 'chapter_book_id' );
		$bookId = ( $bookIdFilter instanceof Filter ) ? $bookIdFilter->getValue() : 'all';

		$cacheKey = $this->wanCache->makeKey( 'bs-bookshelf', 'book-chapters', $bookId );
		$checkKey = self::makeCheckKey( $this->wanCache, $bookId );

		return $this->wanCache->getWithSetCallback(
			$cacheKey,
			WANObjectCache::TTL_DAY,
			function () use ( $params ) {
				return $this->fetchData( $params );
			},
			[ 'checkKeys' => [ $checkKey ] ]
		);
	}

	/**
	 * @param ReaderParams $params
	 * @return Record[]
	 */
	private function fetchData( ReaderParams $params ) {
		$this->data = [];

		$res = $this->db->select(
			$this->getTableNames(),
			$this->getFields(),
			$this->makePreFilterConds( $params ),
			__METHOD__,
			[ 'ORDER BY' => 'chapter_number' ]
		);

		$chapters = iterator_to_array( $res );
		usort( $chapters, static function ( $a, $b ) {
			$aParts = array_map( 'intval', explode( '.', $a->chapter_number ) );
			$bParts = array_map( 'intval', explode( '.', $b->chapter_number ) );

			for ( $i = 0; $i < max( count( $aParts ), count( $bParts ) ); $i++ ) {
				$aVal = $aParts[$i] ?? 0;
				$bVal = $bParts[$i] ?? 0;
				if ( $aVal !== $bVal ) {
					return $aVal <=> $bVal;
				}
			}
			return 0;
		} );

		foreach ( $chapters as $row ) {
			$this->appendRowToData( $row );
		}

		return $this->data;
	}

	/**
	 * @param stdClass $row
	 */
	protected function appendRowToData( stdClass $row ) {
		$this->data[] = new Record( (object)[
			Record::CHAPTER_ID => $row->{Record::CHAPTER_ID},
			Record::CHAPTER_BOOK_ID => $row->{Record::CHAPTER_BOOK_ID},
			Record::CHAPTER_NAMESPACE => $row->{Record::CHAPTER_NAMESPACE},
			Record::CHAPTER_TITLE => $row->{Record::CHAPTER_TITLE},
			Record::CHAPTER_NAME => $row->{Record::CHAPTER_NAME},
			Record::CHAPTER_NUMBER => $row->{Record::CHAPTER_NUMBER},
			Record::CHAPTER_TYPE => $row->{Record::CHAPTER_TYPE},
		] );
	}

	/**
	 * @param WANObjectCache $wanCache
	 * @param string|int $bookId
	 * @return string
	 */
	public static function makeCheckKey( WANObjectCache $wanCache, $bookId ): string {
		return $wanCache->makeKey( 'bs-bookshelf', 'book-chapters-check', (string)$bookId );
	}

	/**
	 * @param WANObjectCache $wanCache
	 * @param string|int $bookId
	 */
	public static function invalidateCache( WANObjectCache $wanCache, $bookId ): void {
		$wanCache->touchCheckKey( self::makeCheckKey( $wanCache, $bookId ) );
		$wanCache->touchCheckKey( self::makeCheckKey( $wanCache, 'all' ) );
	}
}
