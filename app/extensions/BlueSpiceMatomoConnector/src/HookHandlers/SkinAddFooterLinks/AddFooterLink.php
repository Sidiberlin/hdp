<?php

namespace BlueSpice\MatomoConnector\HookHandlers\SkinAddFooterLinks;

use MediaWiki\Config\ConfigFactory;
use MediaWiki\Hook\SkinAddFooterLinksHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use Skin;

class AddFooterLink implements SkinAddFooterLinksHook {

	/** @var ConfigFactory */
	private $configFactory;

	/** @var LinkRenderer */
	private $linkRenderer;

	/**
	 * @param ConfigFactory $configFactory
	 * @param LinkRenderer $linkRenderer
	 */
	public function __construct( ConfigFactory $configFactory, LinkRenderer $linkRenderer ) {
		$this->configFactory = $configFactory;
		$this->linkRenderer = $linkRenderer;
	}

	/** @inheritDoc */
	public function onSkinAddFooterLinks( Skin $skin, string $key, array &$footerItems ) {
		if ( $this->shouldSkip() ) {
			return;
		}

		$link = $this->linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'OptOut' ),
			Message::newFromKey( 'bs-matomoconnector-optout-footerlink' )
		);

		$footerItems[ 'matomoconnector-optout' ] = $link;
	}

	private function shouldSkip() {
		$config = $this->configFactory->makeConfig( 'bsg' );
		if (
			!$config->get( 'MatomoConnectorBaseUrl' ) ||
			!$config->get( 'MatomoConnectorOptOutFooter' )
		) {
			return true;
		}

		return false;
	}

}
