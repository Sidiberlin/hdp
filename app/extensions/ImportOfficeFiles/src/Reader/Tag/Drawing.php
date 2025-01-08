<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag;

use DOMElement;
use DOMNode;
use FormatJson;

class Drawing extends TagProcessorBase {

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	public function process( DOMNode $node ): string {
		$imageProps = [];

		$sizesNode = $node->getElementsByTagName( 'extent' )->item( 0 );
		if ( $sizesNode instanceof DOMElement ) {
			$xWordSize = $sizesNode->getAttribute( 'cx' );
			$yWordSize = $sizesNode->getAttribute( 'cy' );

			$width = $this->convertEmuToPx( $xWordSize );
			$imageProps['width'] = $width;

			$height = $this->convertEmuToPx( $yWordSize );
			$imageProps['height'] = $height;
		}

		$textAlingmentNode = $node->firstChild;
		if ( $textAlingmentNode->nodeName === 'wp:inline' ) {
			$imageProps['inline'] = true;
		} elseif ( $textAlingmentNode->nodeName === 'wp:anchor' ) {
			$imageProps['anchor'] = true;
		}

		$idElement = $node->getElementsByTagName( 'blip' )->item( 0 );
		$wikiText = '';
		if ( $idElement instanceof DOMElement && $idElement->hasAttribute( 'r:embed' ) ) {
			foreach ( $idElement->attributes as $attribute ) {
				if ( $attribute->nodeName === 'r:embed' ) {
					$imageProps['id'] = $attribute->nodeValue;
					$imagePlaceholder = FormatJson::encode( $imageProps );
					$wikiText = "###PRESERVEIMAGE $imagePlaceholder###";
				}
			}
		}
		return $wikiText;
	}

	/**
	 * @param int $emuSize
	 * @return float
	 */
	private function convertEmuToPx( $emuSize ) {
		// 1 inch = 914400 EMU
		// 1pt = 1/72 inch		=>	1pt = 12700 EMU
		// 1px = 0.75pt

		$emuInPt = 12700;

		$ptSize = $emuSize / $emuInPt;

		$pxSize = intval( $ptSize * 0.75 );

		return $pxSize;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return 'drawing';
	}

	/**
	 * @return string
	 */
	public function getNamespace(): string {
		return 'w';
	}
}
