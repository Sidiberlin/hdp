<?php

namespace BlueSpice\Bookshelf\Integration\PDFCreator\Utility;

use DOMDocument;
use DOMElement;
use MediaWiki\Extension\PDFCreator\Utility\BookmarksXMLBuilder as DefaultBookmarksXMLBuilder;
use MediaWiki\Extension\PDFCreator\Utility\ExportPage;

class BookmarksXMLBuilder extends DefaultBookmarksXMLBuilder {

	/**
	 * @param ExportPage[] $pages
	 * @return string
	 */
	public function execute( array $pages ): string {
		$bookmarksDOM = new DOMDocument();
		$bookmarksFragment = $bookmarksDOM->createDocumentFragment();
		$bookmarksFragment->appendXML( '<bookmarks></bookmarks>' );
		$bookmarks = $bookmarksFragment->firstChild;

		for ( $index = 0; $index < count( $pages ); $index++ ) {
			$page = $pages[$index];
			if ( $page->getType() !== 'page' && $page->getType() !== 'raw' ) {
				continue;
			}

			$index = $this->buildItem( $bookmarks, $pages, $index );
		}

		return $bookmarksDOM->saveXML( $bookmarksFragment );
	}

	/**
	 * @param DOMElement $parent
	 * @param array $pages
	 * @param int $curIndex
	 * @return int
	 */
	private function buildChildItems( DOMElement $parent, array $pages, int $curIndex = 0 ): int {
		for ( $index = $curIndex; $index < count( $pages ); $index++ ) {
			$index = $this->buildItem( $parent, $pages, $index );
			if ( !$this->hasSibling( $pages, $index ) ) {
				break;
			}
		}
		return $index;
	}

	/**
	 * @param DOMElement $parent
	 * @param array $pages
	 * @param int $curIndex
	 * @return int
	 */
	private function buildItem( DOMElement $parent, array $pages, int $curIndex ): int {
		/** @var ExportPage */
		$page = $pages[$curIndex];
		$number = $this->getNumber( $page );
		$label = $this->getLabel( $page );
		$level = $this->getLevel( $number );

		$dom = $page->getDOMDocument();
		$id = $this->getIdfromFirstHeading( $dom, $page );
		if ( !$id ) {
			return $curIndex;
		}
		$bookmarkFragment = $parent->ownerDocument->createDocumentFragment();
		$bookmarkFragment->appendXML( $this->getBookmarkXML( $label, $id ) );

		$hasChildren = $this->hasChildren( $level, $pages, $curIndex );
		if ( $hasChildren ) {
			$curIndex = $this->buildChildItems( $bookmarkFragment->firstChild, $pages, $curIndex + 1 );
		}

		$parent->appendChild( $bookmarkFragment );

		return $curIndex;
	}

	/**
	 * @param ExportPage $page
	 * @return string
	 */
	private function getNumber( ExportPage $page ): string {
		$params = $page->getParams();
		$number = ( isset( $params['tocnumber'] ) ) ? $params['tocnumber'] : '';
		return $number;
	}

	/**
	 * @param ExportPage $page
	 * @return string
	 */
	private function getLabel( ExportPage $page ): string {
		$params = $page->getParams();

		$label = '';
		if ( isset( $params['tocnumber'] ) ) {
			$label .= trim( $params['tocnumber'], '.' );
			$label .= '. ';
		}
		if ( isset( $params['toctext'] ) ) {
			$label .= $params['toctext'];
		} else {
			$pageLabel = $page->getLabel();
			$pageLabel = str_replace( trim( $label, ' ' ), '', $pageLabel );
			$label .= trim( $pageLabel, ' ' );
		}
		return htmlspecialchars( $label );
	}

	/**
	 * @param string $number
	 * @return int
	 */
	private function getLevel( string $number ): int {
		return count( explode( '.', trim( $number, '.' ) ) );
	}

	/**
	 * @param int $level
	 * @param ExportPage[] $pages
	 * @param int $curIndex
	 * @return bool
	 */
	private function hasChildren( int $level, array $pages, int $curIndex ): bool {
		if ( !isset( $pages[$curIndex + 1 ] ) ) {
			return false;
		}

		/** @var ExportPage */
		$page = $pages[$curIndex + 1];
		$number = $this->getNumber( $page );
		$nextLevel = $this->getLevel( $number );

		if ( $nextLevel > $level ) {
			return true;
		}
		return false;
	}

	/**
	 * @param array $pages
	 * @param int $curIndex
	 * @return bool
	 */
	private function hasSibling( array $pages, int $curIndex ): bool {
		$nextIndex = $curIndex + 1;
		if ( !isset( $pages[$nextIndex] ) ) {
			return false;
		}
		$pageA = $pages[$curIndex];
		$numberA = $this->getNumber( $pageA );
		$levelA = $this->getLevel( $numberA );

		$pageB = $pages[$nextIndex];
		$numberB = $this->getNumber( $pageB );
		$levelB = $this->getLevel( $numberB );

		return $levelA === $levelB;
	}
}
