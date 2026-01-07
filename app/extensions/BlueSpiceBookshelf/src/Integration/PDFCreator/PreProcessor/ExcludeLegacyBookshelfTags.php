<?php

namespace BlueSpice\Bookshelf\Integration\PDFCreator\PreProcessor;

use DOMXPath;
use MediaWiki\Extension\PDFCreator\IPreProcessor;
use MediaWiki\Extension\PDFCreator\Utility\ExportContext;
use MediaWiki\Extension\PDFCreator\Utility\ExportPage;

class ExcludeLegacyBookshelfTags implements IPreProcessor {

	/**
	 * @param ExportPage[] &$pages
	 * @param array &$images
	 * @param array &$attachments
	 * @param ExportContext|null $context
	 * @param string $module
	 * @param array $params
	 * @return void
	 */
	public function execute(
		array &$pages, array &$images, array &$attachments,
		?ExportContext $context = null, string $module = '', $params = []
	): void {
		foreach ( $pages as $page ) {
			if ( $page instanceof ExportPage === false ) {
				continue;
			}

			$xpath = new DOMXPath( $page->getDOMDocument() );
			$excludeTagElements = $xpath->query(
				'//div[contains(@class, "bs-tag-bs_bookshelf")]',
				$page->getDOMDocument()
			);
			if ( !$excludeTagElements ) {
				continue;
			}

			/** @var DOMElement */
			foreach ( $excludeTagElements as $excludeElement ) {
				$parent = $excludeElement->parentNode;

				if ( !$parent ) {
					continue;
				}

				$parent->removeChild( $excludeElement );
			}
		}
	}

}
