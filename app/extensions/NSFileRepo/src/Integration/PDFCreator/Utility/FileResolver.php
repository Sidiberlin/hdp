<?php

namespace MediaWiki\Extension\NSFileRepo\Integration\PDFCreator\Utility;

use DOMElement;
use File;
use MediaWiki\Extension\PDFCreator\Utility\FileResolver as PDFCreatorFileResolver;
use MediaWiki\Extension\PDFCreator\Utility\ThumbFilenameExtractor;

class FileResolver extends PDFCreatorFileResolver {

	/**
	 * @inheritDoc
	 */
	public function execute( DOMElement $element, string $attrSrc = 'src' ): ?File {
		$pathsForRegex = [
			$this->config->get( 'Server' ),
			$this->config->get( 'ThumbnailScriptPath' ) . "?f=",
			$this->config->get( 'UploadPath' ),
			$this->config->get( 'ScriptPath' )
		];

		$src = $element->getAttribute( $attrSrc );
		if ( strpos( $src, '?' ) ) {
			$src = substr( $src, 0, strpos( $src, '?' ) );
		}
		$srcUrl = urldecode( $src );

		// Extracting the filename
		foreach ( $pathsForRegex as $path ) {
			$srcUrl = preg_replace( "#" . preg_quote( $path, "#" ) . "#", '', $srcUrl );
			$srcUrl = preg_replace( '/(&.*)/', '', $srcUrl );
		}

		$srcFilename = wfBaseName( $srcUrl );

		$thumbFilenameExtractor = new ThumbFilenameExtractor();
		$isThumb = $thumbFilenameExtractor->isThumb( $srcUrl );
		if ( $isThumb ) {
			// HINT: Thumbname-to-filename-conversion taken from includes/Upload/UploadBase.php
			// Check for filenames like 50px- or 180px-, these are mostly thumbnails
			$srcFilename = $thumbFilenameExtractor->extractFilename( $srcUrl );
		}

		/**
		 * Check url for
		 * - thumb
		 * - custom namespace
		 * - archived file
		 */
		$matches = [];
		$file = null;
		preg_match( '#(\/thumb)?\/(\d{4})\/[a-z0-9]{1}\/[a-z0-9]{2}\/(.*)#', $srcUrl, $matches );
		if ( !empty( $matches ) ) {
			$namespace = $matches[2];
			$dummyTitle = $this->titleFactory->newFromText( 'Dummy', $namespace );
			$fileTitle = $this->titleFactory->newFromText( 'File:' . $dummyTitle->getNsText() . ':' . $srcFilename );
			$file = $this->repoGroup->findFile( $fileTitle );
		} else {
			preg_match( '#\/([a-z0-9])\/([a-z0-9]{2})\/(.*)#', $srcUrl, $matches );
			if ( !empty( $matches ) ) {
				$fileTitle = $this->titleFactory->newFromText( $srcFilename, NS_FILE );
				$file = $this->repoGroup->findFile( $fileTitle );
			}
		}

		if ( !$file ) {
			$file = $this->findArchivedFile( $srcFilename );
		}

		return $file ?: null;
	}
}
