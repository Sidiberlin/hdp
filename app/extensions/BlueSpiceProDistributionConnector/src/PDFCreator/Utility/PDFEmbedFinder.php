<?php

namespace BlueSpice\ProDistributionConnector\PDFCreator\Utility;

use DOMDocument;
use DOMXPath;
use MediaWiki\Extension\PDFCreator\Utility\AttachmentFinder;

class PDFEmbedFinder extends AttachmentFinder {

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	protected function find( DOMDocument $dom ): void {
		$xpath = new DOMXPath( $dom );
		$iFrames = $xpath->query(
			'//iframe[contains(@class, "pdf-embed")]',
			$dom
		);

		/** @var FileResolver */
		$fileResolver = $this->getFileResolver();

		/** @var DOMElement */
		foreach ( $iFrames as $iframe ) {
			if ( !$iframe->hasAttribute( 'src' ) ) {
				continue;
			}

			$file = $fileResolver->execute( $iframe, 'src' );
			if ( !$file ) {
				continue;
			}
			$absPath = $file->getLocalRefPath();
			$filename = $file->getName();
			$filename = $this->uncollideFilenames( $filename, $absPath );
			$url = $iframe->getAttribute( 'src' );

			if ( !isset( $this->data[$filename] ) ) {
				$this->data[$filename] = [
					'src' => [ $url ],
					'absPath' => $absPath,
					'filename' => $filename,
				];
			} elseif ( $this->data[$filename]['absPath'] === $absPath ) {
				$urls = &$this->data[$filename]['src'];
				if ( !in_array( $url, $urls ) ) {
					$urls[] = $url;
				}
			}

			$link = $dom->createElement( 'a' );
			$link->setAttribute( 'href', 'attachments/' . $filename );
			$link->setAttribute( 'data-fs-embed-file', 'true' );
			$link->nodeValue = $filename;
			$iframe->parentNode->replaceChild( $link, $iframe );
		}
	}
}
