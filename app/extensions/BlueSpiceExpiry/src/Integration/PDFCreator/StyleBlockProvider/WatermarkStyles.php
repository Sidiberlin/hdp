<?php

namespace BlueSpice\Expiry\Integration\PDFCreator\StyleBlockProvider;

use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Extension\PDFCreator\IStyleBlocksProvider;
use MediaWiki\Extension\PDFCreator\Utility\ExportContext;
use MediaWiki\User\Options\UserOptionsLookup;

class WatermarkStyles implements IStyleBlocksProvider {

	/** @var Config */
	private $config;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/**
	 * @param ConfigFactory $configFactory
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct( ConfigFactory $configFactory, UserOptionsLookup $userOptionsLookup ) {
		$config = $configFactory->makeConfig( 'bsg' );
		$this->config = $config;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * @param string $module
	 * @param ExportContext $context
	 * @return array
	 */
	public function execute( string $module, ExportContext $context ): array {
		if ( !$this->config->get( 'ExpiryEnablePDFWatermark' ) ) {
			return [];
		}

		$userLanguageSetting = $this->userOptionsLookup->getOption(
			$context->getUserIdentity(),
			'language'
		);
		[ $lang ] = explode( '-', $userLanguageSetting );

		$styles = <<<HEREDOC
.pdfcreator-type-page.expired {
	background-image: url('images/bg-expired-$lang.png');
	background-repeat: repeat;
	background-position: top left;
	background-size: auto 6cm;
	min-height: 250px;
}
HEREDOC;
		return [
			'Expiry' => $styles
		];
	}
}
