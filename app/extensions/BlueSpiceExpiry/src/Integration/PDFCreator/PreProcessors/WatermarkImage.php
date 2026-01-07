<?php

namespace BlueSpice\Expiry\Integration\PDFCreator\PreProcessors;

use DOMElement;
use DOMXPath;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Extension\PDFCreator\IPreProcessor;
use MediaWiki\Extension\PDFCreator\Utility\ExportContext;
use MediaWiki\Extension\PDFCreator\Utility\ExportPage;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Options\UserOptionsLookup;

class WatermarkImage implements IPreProcessor {

	/** @var Config */
	private $config;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param ConfigFactory $configFactory
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		ConfigFactory $configFactory, UserOptionsLookup $userOptionsLookup,
		TitleFactory $titleFactory
	) {
		$config = $configFactory->makeConfig( 'bsg' );
		$this->config = $config;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @param ExportPage[] &$pages
	 * @param array &$images
	 * @param array &$attachments
	 * @param ExportContext $context
	 * @param string $module
	 * @param array $params
	 * @return void
	 */
	public function execute( array &$pages, array &$images, array &$attachments,
		ExportContext $context, string $module = '', $params = []
	): void {
		if ( !$this->config->get( 'ExpiryEnablePDFWatermark' ) ) {
			return;
		}

		$this->addExpiryImage( $images, $context );

		/** @var ExportPage */
		foreach ( $pages as $page ) {
			if ( $page->getPrefixedDBKey() === null ) {
				continue;
			}
			$title = $this->titleFactory->newFromDBkey( $page->getPrefixedDBKey() );
			$expiry = \BlueSpice\Expiry\Extension::getExpiryForPage(
				$title->getArticleID()
			);
			if ( !$expiry ) {
				continue;
			}

			$DOMXpath = new DOMXPath( $page->getDOMDocument() );
			$query = "//div[contains(@class, 'pdfcreator-type-page')]";
			foreach ( $DOMXpath->query( $query ) as $element ) {
				if ( $element instanceof DOMElement === false ) {
					continue;
				}
				$element->setAttribute(
					'class',
					$element->getAttribute( 'class' ) . ' expired'
				);
			}
		}
	}

	/**
	 * @param array &$images
	 * @param ExportContext $context
	 * @return void
	 */
	private function addExpiryImage( array &$images, ExportContext $context ): void {
		$userLanguageSetting = $this->userOptionsLookup->getOption(
			$context->getUserIdentity(),
			'language'
		);
		[ $lang ] = explode( '-', $userLanguageSetting );

		$path = MW_INSTALL_PATH . '/extensions/BlueSpiceExpiry/resources/images';

		$images['bg-expired-' . $lang . '.png'] = "$path/bg-expired-$lang.png";
	}
}
