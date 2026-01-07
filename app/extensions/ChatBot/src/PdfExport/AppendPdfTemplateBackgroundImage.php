<?php

namespace ChatBot\PdfExport;

use MediaWiki\Extension\PDFCreator\IPreProcessor;
use MediaWiki\Extension\PDFCreator\Utility\ExportContext;

class AppendPdfTemplateBackgroundImage implements IPreProcessor {

	/**
	 * @inheritDoc
	 */
	public function execute(
		array &$pages,
		array &$images,
		array &$attachments,
		ExportContext $context,
		string $module = '',
		$params = []
	): void {
		if ( !isset( $params['include-image'] ) ) {
			return;
		}

		global $IP;
		$imagePath = $params['include-image'];
		$imageName = basename( $imagePath );
		$images[$imageName] = $IP . $params['include-image'];
	}
}
