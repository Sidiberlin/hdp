<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Component;

use DOMDocument;

class Word2007Rels {

	/**
	 * @param string $path
	 * @return array
	 */
	public function execute( $path ): array {
		$filePath = $path . '/document/word/_rels/document.xml.rels';
		if ( !file_exists( $filePath ) ) {
			return [];
		}

		$dom = new DOMDocument();
		$dom->load( $filePath );
		$relationships = $dom->getElementsByTagName( 'Relationship' );
		$rels = $this->readXML( $relationships );
		return $rels;
	}

	/**
	 * @param DOMNodeList $relationships
	 * @return array
	 */
	private function readXML( $relationships ): array {
		$rels = [];
		foreach ( $relationships as $relationship ) {
			$attributes = $relationship->attributes;
			$rel = [];
			foreach ( $attributes as $attribute ) {
				$rel[$attribute->nodeName] = $attribute->nodeValue;
			}
			$rels[] = $rel;
		}
		return $rels;
	}
}
