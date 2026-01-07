<?php

namespace BlueSpice\Bookshelf\Integration\PDFCreator\StyleBlockProvider;

use MediaWiki\Extension\PDFCreator\IStyleBlocksProvider;
use MediaWiki\Extension\PDFCreator\Utility\ExportContext;

class StyleBlocks implements IStyleBlocksProvider {

	/**
	 * @param string $module
	 * @param ExportContext $context
	 * @return array
	 */
	public function execute( string $module, ExportContext $context ): array {
		$css = [
			'.tocnumber.hidden { display: none; }',
			'.bs-chapter-number.hidden { display: none; }',
			'.mw-headline-number.hidden { display: none; }',
		];

		return [
			'Bookshelf'  => implode( ' ', $css )
		];
	}
}
