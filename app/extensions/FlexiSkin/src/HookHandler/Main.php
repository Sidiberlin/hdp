<?php

namespace MediaWiki\Extension\FlexiSkin\HookHandler;

use Config;
use MediaWiki\Extension\FlexiSkin\FlexiSkinConfig;
use MediaWiki\Extension\FlexiSkin\IFlexiSkin;
use MediaWiki\Extension\FlexiSkin\IFlexiSkinManager;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\Hook\UserLoadAfterLoadFromSessionHook;
use MultiConfig;
use RequestContext;

class Main implements MediaWikiServicesHook, BeforePageDisplayHook, UserLoadAfterLoadFromSessionHook {

	/** @var IFlexiSkinManager */
	private $flexiSkinManager = null;

	/** @var HookContainer */
	private $hookContainer = null;

	/**
	 * Lazy initialization of FlexiSkinManager as a class that implements
	 * `MediaWikiServicesHook` can not use service injection from ObjectFactory
	 * @return IFlexiSkinManager
	 */
	private function getFlextSkinManager(): IFlexiSkinManager {
		if ( $this->flexiSkinManager === null ) {
			$this->flexiSkinManager = MediaWikiServices::getInstance()->getService( 'FlexiSkinManager' );
		}
		return $this->flexiSkinManager;
	}

	/**
	 * Lazy initialization of HookContainer as a class that implements
	 * `MediaWikiServicesHook` can not use service injection from ObjectFactory
	 * @return HookContainer
	 */
	private function getHookContainer(): HookContainer {
		if ( $this->hookContainer === null ) {
			$this->hookContainer = MediaWikiServices::getInstance()->getHookContainer();
		}
		return $this->hookContainer;
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function onMediaWikiServices( $container ) {
		$container->addServiceManipulator(
			'MainConfig',
			static function ( Config $mainConfig ): Config {
				return new MultiConfig( [
					new FlexiSkinConfig(),
					$mainConfig
				] );
			}
		);
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$skinname = $skin->getSkinName();
		$active = $this->getFlextSkinManager()->getActive( $skinname );
		if ( !$active instanceof IFlexiSkin ) {
			return;
		}

		$config = $this->getFlextSkinManager()->getActiveConfig( $skinname );
		if ( !isset( $config['free_css']['css'] ) ) {
			return;
		}

		$style = $config['free_css']['css'];
		$style = str_replace( "\n", '', $style );
		$style = preg_replace( '/\s/', ' ', $style );
		if ( empty( $style ) ) {
			return;
		}

		$out->addInlineStyle( $style );
	}

	/**
	 * This handler is only used to call hook "FlexiSkinAfterLoad" at the same
	 * time as before. Most likely this is not needed and can be removed.
	 * @inheritDoc
	 */
	public function onUserLoadAfterLoadFromSession( $user ) {
		if ( MW_ENTRY_POINT !== 'load' && MW_ENTRY_POINT !== 'index' ) {
			return;
		}
		$skinname = RequestContext::getMain()->getSkin()->getSkinName();
		$active = $this->getFlextSkinManager()->getActive( $skinname );
		if ( !$active instanceof IFlexiSkin ) {
			return;
		}
		$this->getHookContainer()->run( 'FlexiSkinAfterLoad', [ $active ] );
	}
}
