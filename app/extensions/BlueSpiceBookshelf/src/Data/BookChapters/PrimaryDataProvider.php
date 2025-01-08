<?php

namespace BlueSpice\Bookshelf\Data\BookChapters;

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

		$res = $this->db->select(
			'bs_book_chapters',
			'*',
			$filterConds,
			__METHOD__,
			[
				 'ORDER BY' => 'chapter_number'
			]
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

		$bookID = $filterFinder->findByField( 'chapter_book_id' );
		$chapterNamespace = $filterFinder->findByField( 'chapter_namespace' );
		$chapterTitle = $filterFinder->findByField( 'chapter_title' );
		$chapterName = $filterFinder->findByField( 'chapter_name' );
		$chapterType = $filterFinder->findByField( 'chapter_type' );

		if ( $bookID instanceof Filter ) {
			$conds['chapter_book_id'] = $bookID->getValue();
		}
		if ( $chapterNamespace instanceof Filter ) {
			$conds['chapter_namespace'] = $chapterNamespace->getValue();
		}
		if ( $chapterTitle instanceof Filter ) {
			$conds['chapter_title'] = $chapterTitle->getValue();
		}
		if ( $chapterName instanceof Filter ) {
			$conds['chapter_name'] = $chapterName->getValue();
		}
		if ( $chapterType instanceof Filter ) {
			$conds['chapter_type'] = $chapterType->getValue();
		}

		return $conds;
	}

	/**
	 *
	 * @param \stdClass $row
	 */
	protected function appendRowToData( \stdClass $row ) {
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
}
