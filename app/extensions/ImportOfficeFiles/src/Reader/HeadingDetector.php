<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader;

use DOMElement;

class HeadingDetector {

	/**
	 * @var array
	 */
	private $splitStyleNames = [
		'Heading 1',
		'Heading 2',
		'Heading 3',
		'Heading 4',
		'Heading 5',
	];

	/**
	 * @var array
	 */
	private $wikiLevel = [
		'Heading 1' => 2,
		'Heading 2' => 3,
		'Heading 3' => 4,
		'Heading 4' => 5,
		'Heading 5' => 6,
	];

	/**
	 * @param DOMElement $node
	 * @param array $styles
	 * @return void
	 */
	public function detect( $node, $styles ) {
		if ( $node->nodeName !== 'w:p' ) {
			return false;
		}
		$styleElements = $node->getElementsByTagName( 'pStyle' );
		if ( $styleElements->count() > 0 ) {
			$styleElement = $styleElements->item( 0 );
			$styleId = $styleElement->getAttribute( 'w:val' );
			return $this->isHeading( $styleId, $styles );
		}
		return false;
	}

	/**
	 * @param string $styleId
	 * @param array $styles
	 * @return bool|string
	 */
	private function isHeading( $styleId, $styles ) {
		if ( empty( $styles ) ) {
			return false;
		}
		foreach ( $styles as $style ) {
			if ( isset( $style['id'] ) && $style['id'] === $styleId ) {
				if ( !isset( $style['name'] ) ) {
					continue;
				}
				$name = ucfirst( $style['name'] );
				if ( in_array( $name, $this->splitStyleNames ) ) {
					return $this->wikiLevel[$name];
				}
				return false;
			}
		}
		return false;
	}
}
