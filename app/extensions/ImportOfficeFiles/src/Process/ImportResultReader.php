<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Process;

use DOMDocument;
use MediaWiki\Extension\ImportOfficeFiles\Workspace;

class ImportResultReader {

	/**
	 * @var Workspace
	 */
	private $workspace;

	/**
	 * @param Workspace $workspace
	 */
	public function __construct( Workspace $workspace ) {
		$this->workspace = $workspace;
	}

	/**
	 * @return array
	 */
	public function readResult(): array {
		$xmlPath = $this->workspace->getPath() . '/result/import.xml';

		$dom = new DOMDocument();
		$dom->load( $xmlPath );

		$pageNodes = $dom->getElementsByTagName( 'page' );

		$pages = [];
		foreach ( $pageNodes as $pageNode ) {
			/** @var $pageNode \DOMElement */
			$titleNode = $pageNode->getElementsByTagName( 'title' )->item( 0 );
			$title = $titleNode->nodeValue;

			$textNodes = $pageNode->getElementsByTagName( 'text' );
			if ( $textNodes->length ) {
				$text = $textNodes->item( 0 )->nodeValue;
			} else {
				$text = '';
			}

			$pages[$title]['content'] = $text;
			$pages[$title]['files'] = $this->readImages( $text );
		}

		return $pages;
	}

	/**
	 * @param string $text
	 * @return array
	 */
	private function readImages( string $text ): array {
		$matches = [];
		preg_match_all( '#\[\[File:(.*?)\|.*?\]\]#', $text, $matches );

		$images = $matches[1];

		return $images;
	}

}
