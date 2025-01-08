<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader;

use DOMDocument;
use DOMElement;

class DocumentSplitter {

	/**
	 * Returns an array with heading level and split file path
	 * array(4) {
	 *	[0]=>
	 *		array(2) {
	 *			["level"]=> int(0)
	 *			["file"]=> string(44) "../tmp//test/raw/part-1.xml"
	 *		}
	 *	[1]=>
	 *		array(2) {
	 *			["level"]=> int(2)
	 *			["file"]=> string(44) "../tmp//test/raw/part-2.xml"
	 *		}
	 *	}
	 *
	 * @param string $path
	 * @param DOMDocument $dom
	 * @param array $styles
	 * @param bool $verbose <tt>true</tt> if "echo" output is needed, <tt>false</tt> otherwise
	 * @return array
	 */
	public function split( $path, DOMDocument $dom, $styles, bool $verbose = false ): array {
		$rawFileList = [];

		if ( empty( $styles ) ) {
			if ( $verbose ) {
				echo "No styles set\n";
			}
			return [ $dom ];
		}

		$status = wfMkdirParents( "$path/raw", null, get_class( $this ) );

		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		$childNodes = $body->childNodes;
		if ( $verbose ) {
			echo "Child nodes: " . count( $childNodes ) . "\n";
			echo "Raw segments: ";
		}

		$headingDetector = new HeadingDetector();

		$counter = 1;
		$heading = 0;
		$elBuffer = [];
		$newDom = new DOMDocument();
		$dom->createAttributeNS( 'w', 'w' );
		$newDom->loadXML( '<body></body>' );
		foreach ( $childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement === false ) {
				continue;
			}
			$nextHeading = $headingDetector->detect( $childNode, $styles );
			if ( $nextHeading ) {
				foreach ( $elBuffer as $bufferedEl ) {
					$copiedNode = $newDom->importNode( $bufferedEl['node'], true );
					$newDom->firstChild->appendChild( $copiedNode );
				}
				$rawFileList[] = [
					'level' => $heading,
					'file' => "$path/raw/part-$counter.xml"
				];
				$heading = $nextHeading;
				$newDom->save( "$path/raw/part-$counter.xml" );
				$counter++;

				$elBuffer = [];
				$newDom = new DOMDocument();
				$dom->createAttributeNS( 'w', 'w' );
				$newDom->loadXML( '<body></body>' );
			}
			$elBuffer[] = [
				'level' => $heading,
				'node' => $childNode
			];
		}

		if ( !empty( $elBuffer ) ) {
			foreach ( $elBuffer as $bufferedEl ) {
				$copiedNode = $newDom->importNode( $bufferedEl['node'], true );
				$newDom->firstChild->appendChild( $copiedNode );
			}
			$rawFileList[] = [
				'level' => $bufferedEl['level'],
				'file' => "$path/raw/part-$counter.xml"
			];
			$newDom->save( "$path/raw/part-$counter.xml" );
		}

		if ( $verbose ) {
			echo count( $rawFileList ) . " parts\n";
		}

		return $rawFileList;
	}
}
