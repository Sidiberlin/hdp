<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Component;

use DOMDocument;
use DOMXPath;

class Word2007Styles {

	/**
	 * @param string $path
	 * @return array
	 */
	public function execute( $path ): array {
		$filePath = $path . '/document/word/styles.xml';
		if ( !file_exists( $filePath ) ) {
			return [];
		}

		$dom = new DOMDocument();
		$dom->load( $filePath );
		$xpath = new DOMXPath( $dom );
		$styleElements = $xpath->query( '//w:style' );
		$styles = $this->readXML( $styleElements );
		return $styles;
	}

	/**
	 * @param DOMNodeList $styleElements
	 * @return array
	 */
	private function readXML( $styleElements ): array {
		$styles = [];
		foreach ( $styleElements as $styleElement ) {
			$style = [];
			$style['type'] = $styleElement->getAttribute( 'w:type' );
			$style['id'] = $styleElement->getAttribute( 'w:styleId' );

			$childNodes = $styleElement->childNodes;
			foreach ( $childNodes as $childNode ) {
				$nodeName = str_replace( 'w:', '', $childNode->nodeName );
				if ( $childNode->hasAttribute( 'w:val' ) ) {
					$style[$nodeName] = $childNode->getAttribute( 'w:val' );
				}
			}
			$styles[] = $style;
		}
		return $styles;
	}
}
