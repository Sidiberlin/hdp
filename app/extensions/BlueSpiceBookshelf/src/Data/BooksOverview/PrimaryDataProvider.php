<?php

namespace BlueSpice\Bookshelf\Data\BooksOverview;

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
		return [ 'b' => 'bs_books' ];
	}

	/**
	 * @return string[]
	 */
	protected function getFields() {
		return [ 'b.book_name', 'b.book_namespace', 'b.book_title' ];
	}

	/**
	 * @return array
	 */
	protected function getDefaultConds() {
		return [ 'book_type' => 'public' ];
	}

	/**
	 * @param ReaderParams $params
	 * @return Record[]
	 */
	public function makeData( $params ) {
		$cacheKey = $this->wanCache->makeKey(
			'bs-bookshelf', 'books-overview', md5( serialize( $params->getFilter() ) )
		);
		$checkKey = self::makeCheckKey( $this->wanCache );

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
			$this->makePreOptionConds( $params )
		);

		foreach ( $res as $row ) {
			$this->appendRowToData( $row );
		}

		return $this->data;
	}

	/**
	 * @param ReaderParams $params
	 * @return array
	 */
	protected function makePreFilterConds( ReaderParams $params ) {
		$conds = $this->getDefaultConds();

		$filterFinder = new FilterFinder( $params->getFilter() );

		$bookNamespace = $filterFinder->findByField( 'page_namespace' );
		$bookTitle = $filterFinder->findByField( 'page_title' );

		if ( $bookNamespace instanceof Filter ) {
			$conds['page_namespace'] = $bookNamespace->getValue();
			$bookNamespace->setApplied();
		}
		if ( $bookTitle instanceof Filter ) {
			$conds['page_title'] = $bookTitle->getValue();
			$bookTitle->setApplied();
		}

		return $conds;
	}

	/**
	 * @param stdClass $row
	 */
	protected function appendRowToData( stdClass $row ) {
		$this->data[] = new Record( (object)[
			Record::DISPLAYTITLE => $row->book_name,
			Record::BOOK_NAMESPACE => $row->book_namespace,
			Record::BOOK_TITLE => $row->book_title
		] );
	}

	/**
	 * @param WANObjectCache $wanCache
	 * @return string
	 */
	public static function makeCheckKey( WANObjectCache $wanCache ): string {
		return $wanCache->makeKey( 'bs-bookshelf', 'books-overview-check' );
	}

	/**
	 * @param WANObjectCache $wanCache
	 */
	public static function invalidateCache( WANObjectCache $wanCache ): void {
		$wanCache->touchCheckKey( self::makeCheckKey( $wanCache ) );
	}
}
