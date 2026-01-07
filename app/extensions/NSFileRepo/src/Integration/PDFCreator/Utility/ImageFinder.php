<?php

namespace MediaWiki\Extension\NSFileRepo\Integration\PDFCreator\Utility;

use MediaWiki\Extension\PDFCreator\Utility\ImageFinder as PDFCreatorImageFinder;

class ImageFinder extends PDFCreatorImageFinder {

	/**
	 * @inheritDoc
	 */
	protected function getFileResolver() {
		return new FileResolver(
			$this->config, $this->repoGroup, $this->titleFactory
		);
	}
}
