<?php

use MediaWiki\Api\ApiMain;
use MediaWiki\Json\FormatJson;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class ApiBookshelfStore extends BSApiExtJSStoreBase {

	/**
	 *
	 * @var LinkRenderer
	 */
	protected $linkRenderer = null;

	/**
	 *
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 */
	public function __construct( $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );

		$this->linkRenderer = $this->services->getLinkRenderer();
	}

	/**
	 *
	 * @param string $sQuery
	 * @return stdClass[]
	 */
	protected function makeData( $sQuery = '' ) {
		$aAllBooks = array_merge(
			$this->fetchBookNamespaceBooks(),
			$this->fetchTempBooksFromParam()
		);
		$aFilteredBooks = [];
		$sLcQuery = strtolower( $sQuery );
		foreach ( $aAllBooks as $oDataSet ) {
			$sLcDisplayText = strtolower( $oDataSet->book_displaytext );
			if ( !empty( $sLcQuery ) && strpos( $sLcDisplayText, $sLcQuery ) === false ) {
				continue;
			}
			$aFilteredBooks[] = $oDataSet;
		}

		return $aFilteredBooks;
	}

	/**
	 *
	 * @return string
	 */
	protected function getDescription() {
		return 'ExtJS store backend for Bookshelf';
	}

	/**
	 *
	 * @return array
	 */
	public function getAllowedParams() {
		$aParams = parent::getAllowedParams();
		// TODO: Add 'user' field to allow fechting for different users
		$aParams['tempBooks'] = [
			ParamValidator::PARAM_TYPE => 'string',
			ParamValidator::PARAM_REQUIRED => false,
			ParamValidator::PARAM_DEFAULT => '{}',
		];
		return $aParams;
	}

	/**
	 *
	 * @return array
	 */
	public function getParamDescription() {
		// TODO: Add 'user' field to allow fechting for different users
		return parent::getParamDescription();
	}

	/**
	 *
	 * @return array
	 */
	public function fetchBookNamespaceBooks() {
		$aData = [];

		if ( $this->services->getPermissionManager()
			->userCan(
				'read',
				$this->getUser(),
				Title::makeTitle( NS_BOOK, 'X' )
			) === false
		) {
			return $aData;
		}

		$dbr = $this->getDB();
		$res = $dbr->select(
			[ 'page', 'bs_books' ],
			[ 'page_id', 'page_title', 'page_namespace', 'book_id' ],
			[ 'page_namespace' => NS_BOOK ],
			__METHOD__,
			[ 'ORDER BY' => 'page_title' ],
			[
				'bs_books' => [
					'INNER JOIN',
					'book_title = page_title'
				]
			]
		);

		foreach ( $res as $row ) {
			$oDataSet = $this->makeDataSet( $row );
			if ( $oDataSet === null ) {
				continue;
			}
			$oDataSet->book_type = 'ns_book';
			$aData[] = $oDataSet;
		}

		return $aData;
	}

	/**
	 *
	 * @param stdClass $row
	 * @return stdClass|null
	 */
	public function makeDataSet( $row ) {
		$oTitle = Title::newFromID( $row->page_id );
		$oPHP = PageHierarchyProvider::getInstanceFor( $oTitle->getPrefixedText() );
		$aTOC = $oPHP->getExtendedTOCArray();

		$sFirstChapterPrefixedText = null;
		$oFirstTitle = $oTitle;

		if ( isset( $aTOC[0] ) ) {
			$aFirstTitle = $aTOC[0];
			$oFirstTitle = Title::newFromText( $aFirstTitle['title'] );
			$sFirstChapterPrefixedText = $oFirstTitle instanceof Title ?
				$oFirstTitle->getPrefixedText() : $aFirstTitle['title'];
		}

		$oBook = new stdClass();
		$oBook->page_id = (int)$row->page_id;
		$oBook->page_title = $row->page_title;
		$oBook->page_namespace = (int)$row->page_namespace;
		$oBook->book_first_chapter_prefixedtext = $sFirstChapterPrefixedText;
		$oBook->book_first_chapter_link
			= $this->linkRenderer->makeLink( $oFirstTitle, $oTitle->getText() );
		$oBook->book_prefixedtext = $oTitle->getPrefixedText();
		$oBook->book_displaytext = $oTitle->getText();
		$oBook->book_meta = $oPHP->getBookMeta();
		$oBook->book_id = (int)$row->book_id;

		// maybe for future or optional? Include full book tree in response. Very expensive!
		// $oBook->book_tree = $oPHP->getExtendedTOCJSON();

		// This hook is DEPRECATED! Use hooks from base class instead!
		$this->services->getHookContainer()->run(
			'BSBookshelfManagerGetBookDataRow',
			[
				$oTitle,
				$oBook
			],
			[ 'deprecatedVersion' => '3.1' ]
		);

		return $oBook;
	}

	private function fetchTempBooksFromParam() {
		$value = $this->getParameter( 'tempBooks' );

		if ( !$value ) {
			return [];
		}

		$data = [];
		$decoded = FormatJson::decode( $value, 1 );
		foreach ( $decoded as $name => $content ) {
			$checkTitle = Title::newFromText( $name );
			if ( in_array( $checkTitle->getNamespace(), [ NS_BOOK, NS_USER ] ) ) {
				// Actual book with title
				continue;
			}

			$dataset = $this->makeDataSetForTemp( $name, $content );
			if ( $dataset === null ) {
				continue;
			}
			$dataset->book_type = 'local_storage';
			$data[] = $dataset;
		}

		return $data;
	}

	/**
	 * @param string $name
	 * @param mixed $content
	 * @return stdClass
	 */
	private function makeDataSetForTemp( $name, $content ) {
		$php = DynamicPageHierarchyProvider::getInstanceFor( $name, [ 'content' => $content ] );
		$toc = $php->getExtendedTOCArray();

		$sFirstChapterPrefixedText = null;

		$book = new stdClass();
		$book->page_id = -1;
		$book->page_title = $name;
		$book->page_namespace = -5;

		if ( isset( $toc[0] ) ) {
			$firstTitle = $toc[0];
			$firstTitle = Title::newFromText( $firstTitle['title'] );
			$book->book_first_chapter_prefixedtext = $firstTitle instanceof Title ?
				$firstTitle->getPrefixedText() : $firstTitle['title'];
			$book->book_first_chapter_link
				= $this->linkRenderer->makeLink( $firstTitle, $name );
		}

		$book->book_prefixedtext = $name;
		$book->book_displaytext = $name;
		$book->book_meta = $php->getBookMeta();

		// This hook is DEPRECATED! Use hooks from base class instead!
		$this->services->getHookContainer()->run(
			'BSBookshelfManagerGetBookDataRow',
			[
				Title::newFromText( 'invalid', -1 ),
				$book
			],
			[ 'deprecatedVersion' => '3.1' ]
		);

		return $book;
	}
}
