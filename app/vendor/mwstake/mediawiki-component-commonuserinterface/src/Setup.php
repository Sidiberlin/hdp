<?php

namespace MWStake\MediaWiki\Component\CommonUserInterface;

use Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Context as ResourceLoaderContext;
use OutputPage;
use Skin;

class Setup {

	/**
	 *
	 * @param string &$data
	 * @param Skin $skin
	 * @return bool
	 */
	public static function onSkinAfterContent( &$data, Skin $skin ) {
		$services = MediaWikiServices::getInstance();
		/** @var SkinSlotRendererFactory */
		$skinSlotRendererFactory = $services->getService( 'MWStakeCommonUISkinSlotRendererFactory' );
		/** @var GenericSkinSlotRenderer */
		$skinSlotRenderer = $skinSlotRendererFactory->create( 'skinAfterContent' );
		$data .= $skinSlotRenderer->getHtml( [] );

		return true;
	}

	/**
	 *
	 * @param string &$siteNotice
	 * @param Skin $skin
	 * @return bool
	 */
	public static function onSiteNoticeAfter( &$siteNotice, $skin ) {
		$services = MediaWikiServices::getInstance();
		/** @var SkinSlotRendererFactory */
		$skinSlotRendererFactory = $services->getService( 'MWStakeCommonUISkinSlotRendererFactory' );
		/** @var GenericSkinSlotRenderer */
		$skinSlotRenderer = $skinSlotRendererFactory->create( 'siteNoticeAfter' );
		$siteNotice .= $skinSlotRenderer->getHtml( [] );

		return true;
	}

	/**
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return bool
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		$services = MediaWikiServices::getInstance();
		/** @var ComponentManager */
		$componentManager = $services->getService( 'MWStakeCommonUIComponentManager' );

		$out->addModules( $componentManager->getRLModules() );
		$out->addModuleStyles( $componentManager->getRLModuleStyles() );

		$out->addModules( 'mwstake.component.commonui' );
		return true;
	}

	/**
	 * @param ResourceLoaderContext $context
	 * @param Config $config
	 * @param array $callbackParams
	 * @return array
	 */
	public static function makeClientSideRendererConfig( ResourceLoaderContext $context,
	Config $config, $callbackParams ) {
		$services = MediaWikiServices::getInstance();
		/** @var ComponentRendererFactory */
		$rendererFactory = $services->get( 'MWStakeCommonUIComponentRendererFactory' );
		$rendererConfig = [];
		$renderers = $rendererFactory->getAllRenderers();
		foreach ( $renderers as $key => $renderer ) {
			$rendererConfig[$key] = [
				// TODO: Make this "mustache independent"
				'templateKey' => sha1( $renderer->getTemplatePathname() ) . '.mustache',
				'modules' => $renderer->getRLModules(),
				'moduleStyles' => $renderer->getRLModules()
			];
		}

		return $rendererConfig;
	}
}
