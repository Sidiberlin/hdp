<?php

namespace ChatBot\PdfExport;

use MediaWiki\Extension\PDFCreator\Module\Batch;
use MediaWiki\Extension\PDFCreator\Utility\ExportContext;
use MediaWiki\Extension\PDFCreator\Utility\ExportPage;
use MediaWiki\Extension\PDFCreator\Utility\Template;

class ChatbotPdfExportModuleProvider extends Batch {

	/**
	 * @param array $pageSpecs
	 * @param array $options
	 * @param Template $template
	 * @param ExportContext $context
	 * @param string $workspace
	 * @param array $params
	 *
	 * @return array
	 */
	protected function getPages(
		array $pageSpecs,
		array $options,
		Template $template,
		ExportContext $context,
		string $workspace,
		array $params = []
	): array {
		return [ new ExportPage( '', $params['dom'], '' ) ];
	}
}
