<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader;

use DOMDocument;
use DOMText;

class DocumentPreprocessor {

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	public function execute( DOMDocument $dom ) {
		$this->replaceSdtContentNode( $dom );
		$this->replaceSdtNode( $dom );
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function replaceSdtContentNode( DOMDocument $dom ): void {
		$stdContentNodes = $dom->getElementsByTagName( 'sdtContent' );

		$nonLiveNodes = [];
		foreach ( $stdContentNodes as $node ) {
			$nonLiveNodes[] = $node;
		}

		foreach ( $nonLiveNodes as $nonLiveNode ) {
			$fragment = $nonLiveNode->ownerDocument->createDocumentFragment();

			foreach ( $nonLiveNode->childNodes as $node ) {
				$newNode = $node->cloneNode( true );
				if ( $newNode instanceof DOMText === false ) {
					$fragment->appendChild( $newNode );
				}
			}

			$nonLiveNode->parentNode->replaceChild( $fragment, $nonLiveNode );
		}
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function replaceSdtNode( DOMDocument $dom ): void {
		$stdNodes = $dom->getElementsByTagName( 'sdt' );

		$nonLiveNodes = [];
		foreach ( $stdNodes as $node ) {
			$nonLiveNodes[] = $node;
		}

		foreach ( $nonLiveNodes as $nonLiveNode ) {
			$fragment = $nonLiveNode->ownerDocument->createDocumentFragment();

			foreach ( $nonLiveNode->childNodes as $node ) {
				if ( $node->nodeName === 'w:sdtPr' ) {
					continue;
				}

				if ( $node->nodeName === 'w:sdtEndPr' ) {
					continue;
				}

				$newNode = $node->cloneNode( true );
				if ( $newNode instanceof DOMText === false ) {
					$fragment->appendChild( $newNode );
				}
			}

			$nonLiveNode->parentNode->replaceChild( $fragment, $nonLiveNode );
		}
	}
}
