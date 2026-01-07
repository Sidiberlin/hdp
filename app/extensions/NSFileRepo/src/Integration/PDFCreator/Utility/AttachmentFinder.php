<?php

namespace MediaWiki\Extension\NSFileRepo\Integration\PDFCreator\Utility;

use MediaWiki\Extension\PDFCreator\Utility\AttachmentFinder as PDFCreatorAttachmentFinder;

class AttachmentFinder extends PDFCreatorAttachmentFinder {

	/**
	 * @return void
	 */
	protected function getFileResolver() {
		return new FileResolver(
			$this->config, $this->repoGroup, $this->titleFactory
		);
	}
}
