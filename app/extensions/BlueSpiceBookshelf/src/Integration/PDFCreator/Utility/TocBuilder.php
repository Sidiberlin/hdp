<?php

namespace BlueSpice\Bookshelf\Integration\PDFCreator\Utility;

use DOMDocument;
use DOMElement;
use MediaWiki\Extension\PDFCreator\PDFCreator;
use MediaWiki\Extension\PDFCreator\Utility\ExportPage;
use MediaWiki\Extension\PDFCreator\Utility\TocBuilder as DefaultTocBuilder;

class TocBuilder extends DefaultTocBuilder {

	/**
	 * @param ExportPage[] $pages
	 * @param bool $embedPageToc
	 * @return array
	 */
	public function execute( array $pages, bool $embedPageToc = false ): array {
		$tocLabel = $this->getPageLabelMsg();

		$container = $this->getTocDOMContainer( $pages, $embedPageToc );
		$dom = $container->ownerDocument;
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		$body->appendChild( $container );

		$tocPage = new ExportPage(
			'toc',
			$dom,
			$tocLabel->text()
		);
		array_unshift( $pages, $tocPage );

		return $pages;
	}

	/**
	 * @param array $pages
	 * @param bool $embedPageToc
	 * @return string
	 */
	public function getHtml( array $pages, bool $embedPageToc = false ): string {
		$container = $this->getTocDOMContainer( $pages, $embedPageToc );
		return $container->ownerDocument->saveHTML( $container );
	}

	/**
	 * @param array $pages
	 * @param bool $embedPageToc
	 * @return DOMElement
	 */
	private function getTocDOMContainer( array $pages, bool $embedPageToc = false ): DOMElement {
		$dom = new DOMDocument();
		$dom->loadXML( PDFCreator::HTML_STUB );

		$container = $dom->createElement( 'div' );
		$container->setAttribute( 'class', 'pdfcreator-page pdfcreator-type-toc bs-bookshelf-toc' );

		$ul = $dom->createElement( 'ul' );
		$ul->setAttribute( 'class', 'toc' );

		for ( $index = 0; $index < count( $pages ); $index++ ) {
			$page = $pages[$index];
			if ( $page->getType() === 'intro' || $page->getType() === 'outro' ) {
				continue;
			}
			$index = $this->buildListItem( $ul, $pages, $embedPageToc, $index );
		}

		$container->appendChild( $ul );

		return $container;
	}

	/**
	 * @param DOMElement $ul
	 * @param array $pages
	 * @param bool $embedPageToc
	 * @param int $curIndex
	 * @return int
	 */
	private function buildChildrenList( DOMElement $ul, array $pages, bool $embedPageToc, int $curIndex = 0 ): int {
		for ( $index = $curIndex; $index < count( $pages ); $index++ ) {
			$index = $this->buildListItem( $ul, $pages, $embedPageToc, $index );
			if ( !$this->hasSibling( $pages, $index ) ) {
				break;
			}
		}
		return $index;
	}

	/**
	 * @param DOMElement $ul
	 * @param ExportPage[] $pages
	 * @param bool $embedPageToc
	 * @param int $curIndex
	 * @return int
	 */
	private function buildListItem( DOMElement $ul, array $pages, bool $embedPageToc, int $curIndex ): int {
		/** @var ExportPage */
		$page = $pages[$curIndex];
		$number = $this->getNumber( $page );
		$label = $this->getLabel( $page );
		$level = $this->getLevel( $number );

		$li = $ul->ownerDocument->createElement( 'li' );
		$li->setAttribute( 'class', 'toclevel-' . $level );

		if ( $page->getPrefixedDBKey() ) {
			$this->setNewClass( $li, $page->getPrefixedDBKey() );
		}

		$this->createLink( $li, $number, $label, $page->getUniqueId() );

		$hasChildren = $this->hasChildren( $level, $pages, $curIndex );
		if ( $hasChildren ) {
			$childrenUl = $li->ownerDocument->createElement( 'ul' );
			$curIndex = $this->buildChildrenList( $childrenUl, $pages, $embedPageToc, $curIndex + 1 );
			$li->appendChild( $childrenUl );
		}

		$ul->appendChild( $li );

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

		if ( isset( $params['toctext'] ) ) {
			$label = $params['toctext'];
		} else {
			$number = $this->getNumber( $page );
			$pageLabel = $page->getLabel();
			$pageLabel = str_replace( trim( $number, ' ' ), '', $pageLabel );
			$label = trim( $pageLabel, ' ' );
		}

		return $label;
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

	/**
	 * @param DOMElement $li
	 * @param string $number
	 * @param string $label
	 * @param string $uniqueId
	 * @return void
	 */
	private function createLink( DOMElement $li, string $number, string $label, string $uniqueId ): void {
		$a = $li->ownerDocument->createElement( 'a' );
		$a->setAttribute( 'class', 'toc-link' );
		$a->setAttribute( 'href', '#' . $uniqueId );

		$tocNumber = $a->appendChild(
			$a->ownerDocument->createElement( 'span' )
		);
		$tocNumber->setAttribute( 'class', 'tocnumber' );
		$number = trim( $number, '.' );
		if ( $number !== '' ) {
			$tocNumber->appendChild( $tocNumber->ownerDocument->createTextNode( $number . '.' ) );
		}

		$tocText = $a->appendChild(
			$a->ownerDocument->createElement( 'span' )
		);
		$tocText->setAttribute( 'class', 'toctext' );
		$tocText->appendChild( $tocText->ownerDocument->createTextNode( $label ) );

		$a->appendChild( $tocNumber );
		$a->appendChild( $tocText );
		$li->appendChild( $a );
	}

}
