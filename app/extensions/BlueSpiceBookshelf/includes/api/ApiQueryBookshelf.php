<?php

use MediaWiki\Api\ApiQueryBase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class ApiQueryBookshelf extends ApiQueryBase {

	/**
	 *
	 * @param string $query
	 * @param string $moduleName
	 */
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'bsbs' );
	}

	public function execute() {
		$data = [];
		$services = MediaWikiServices::getInstance();
		$permManager = $services->getPermissionManager();

		if ( $permManager->userCan(
				'read',
				$this->getUser(),
				Title::makeTitle( NS_BOOK, 'X' )
			) === false
		) {
			return;
		}

		$dbr = $services->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$res = $dbr->select(
			'page',
			[ 'page_id', 'page_title', 'page_namespace' ],
			[ 'page_namespace' => NS_BOOK ],
			__METHOD__,
			[ 'ORDER BY' => 'page_title' ]
		);

		foreach ( $res as $row ) {
			$oTitle = Title::newFromID( $row->page_id );
			$oPHP = PageHierarchyProvider::getInstanceFor( $oTitle->getPrefixedText() );
			$aTOC = $oPHP->getExtendedTOCArray();

			if ( !isset( $aTOC[0] ) ) {
				continue;
			}

			$aFirstTitle = $aTOC[0];
			$oFirstTitle = Title::newFromText( $aFirstTitle['title'] );

			if ( $permManager->userCan(
					'read',
					$this->getUser(),
					$oFirstTitle
				) === false
			) {
				continue;
			}

			$oBook = new stdClass();
			$oBook->page_id = $row->page_id;
			$oBook->page_title = $row->page_title;
			$oBook->page_namespace = $row->page_namespace;
			$oBook->book_first_chapter_prefixedtext = $oFirstTitle->getPrefixedText();
			$oBook->book_prefixedtext = $oTitle->getPrefixedText();
			$oBook->type = 'ns_book';
			$oBook->book_meta = $oPHP->getBookMeta();
			# $oBook->book_tree = $oPHP->getExtendedTOCJSON();

			$data[] = (array)$oBook;
		}

		$result = $this->getResult();
		$result->setIndexedTagName( $data, 'book' );
		$result->addValue( 'query', $this->getModuleName(), $data );
	}
}
