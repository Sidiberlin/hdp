<?php

namespace BlueSpice\Bookshelf\Data\BooksOverview;

use MWStake\MediaWiki\Component\DataStore\Filter;
use MWStake\MediaWiki\Component\DataStore\FilterFinder;

class PrimaryDataProvider extends \BlueSpice\Data\Settings\PrimaryDataProvider {

	/**
	 *
	 * @param ReaderParams $params
	 * @return Record[]
	 */
	public function makeData( $params ) {
		$this->data = [];

		$filterConds = $this->makePreFilterConds( $params->getFilter() );
		$filterConds['book_type'] = 'public';

		$res = $this->db->select(
			[ 'b' => 'bs_books' ],
			[ 'b.book_name', 'b.book_namespace', 'b.book_title', ],
			$filterConds,
			__METHOD__
		);

		foreach ( $res as $row ) {
			$this->appendRowToData( $row );
		}

		return $this->data;
	}

	/**
	 *
	 * @param Filter[] $preFilters
	 * @return array
	 */
	protected function makePreFilterConds( $preFilters ) {
		$conds = [];

		$filterFinder = new FilterFinder( $preFilters );

		$bookNamespace = $filterFinder->findByField( 'page_namespace' );
		$bookTitle = $filterFinder->findByField( 'page_title' );

		if ( $bookNamespace instanceof Filter ) {
			$conds['page_namespace'] = $bookNamespace->getValue();
		}
		if ( $bookTitle instanceof Filter ) {
			$conds['page_title'] = $bookTitle->getValue();
		}

		return $conds;
	}

	/**
	 *
	 * @param \stdClass $row
	 */
	protected function appendRowToData( \stdClass $row ) {
		$this->data[] = new Record( (object)[
			Record::DISPLAYTITLE => $row->book_name,
			Record::BOOK_NAMESPACE => $row->book_namespace,
			Record::BOOK_TITLE => $row->book_title
		] );
	}
}
