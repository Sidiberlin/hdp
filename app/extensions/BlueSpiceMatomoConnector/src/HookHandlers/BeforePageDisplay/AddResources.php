<?php

namespace BlueSpice\MatomoConnector\HookHandlers\BeforePageDisplay;

use BlueSpice\MatomoConnector\CustomDimensions;
use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Message\Message;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Utils\UrlUtils;

class AddResources implements BeforePageDisplayHook {

	/** @var ConfigFactory */
	private $configFactory;

	/** @var UrlUtils */
	private $urlUtils;

	/** @var CustomDimensions */
	private $customDimensions;

	/** @var string */
	private $matomoBaseUrl;

	/** @var string */
	private $siteID;

	/**
	 * @param ConfigFactory $configFactory
	 * @param UrlUtils $urlUtils
	 * @param CustomDimensions $customDimensions
	 */
	public function __construct(
		ConfigFactory $configFactory, UrlUtils $urlUtils, CustomDimensions $customDimensions
	) {
		$this->configFactory = $configFactory;
		$this->urlUtils = $urlUtils;
		$this->customDimensions = $customDimensions;
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		$config = $this->configFactory->makeConfig( 'bsg' );
		$matomoBaseUrl = $config->get( 'MatomoConnectorBaseUrl' ) ?? '';
		$this->matomoBaseUrl = rtrim( $matomoBaseUrl, '/' );
		$this->siteID = $config->get( 'MatomoConnectorSiteID' );

		if ( !$this->matomoBaseUrl || !$this->siteID ) {
			return;
		}

		$matomoBaseUrlParts = $this->urlUtils->parse( $this->matomoBaseUrl );
		if ( !$matomoBaseUrlParts ) {
			return;
		}

		$matomoBaseUrlHost = $matomoBaseUrlParts['host'];
		$out->getCSP()->addScriptSrc( $matomoBaseUrlHost );

		$this->customDimensions->checkAndCreateCustomDimensions();

		$this->setCustomDimensionsForPage( $out, $config );

		$out->addJsConfigVars( [
			'bsgMatomoConnectorBaseUrl' => $this->matomoBaseUrl,
			'bsgMatomoConnectorsiteID' => $this->siteID
		] );

		$out->addModules( [ 'ext.bluespice.matomoConnector' ] );
	}

	/**
	 * @param OutputPage $out
	 * @param Config $config
	 */
	private function setCustomDimensionsForPage( OutputPage $out, Config $config ) {
		$title = $out->getTitle();
		if ( !$title ) {
			return;
		}

		$pageNamespace = $title->getNsText();
		if ( $title->getNamespace() === NS_MAIN ) {
			// Normalise NS_MAIN = Page
			$pageNamespace = Message::newFromKey( 'nstab-main' )->inContentLanguage()->text();
		}

		$dimensionIDs = $config->get( 'MatomoConnectorCustomDimensionIDMap' );
		$dimensions[] = [
			'id' => $dimensionIDs['page_namespace'],
			'value' => $pageNamespace
		];

		$out->addJsConfigVars( [
			'bsgMatomoConnectorCustomDimensionValues' => $dimensions
		] );
	}
}
