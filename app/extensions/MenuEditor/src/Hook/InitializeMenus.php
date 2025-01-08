<?php

namespace MediaWiki\Extension\MenuEditor\Hook;

use MediaWiki;
use MediaWiki\Extension\MenuEditor\MenuFactory;
use MediaWiki\Hook\BeforeInitializeHook;
use OutputPage;
use Title;
use User;
use WebRequest;

class InitializeMenus implements BeforeInitializeHook {
	/** @var MenuFactory */
	private $menuFactory;

	/**
	 * @param MenuFactory $menuFactory
	 */
	public function __construct( MenuFactory $menuFactory ) {
		$this->menuFactory = $menuFactory;
	}

	/**
	 * @param Title $title
	 * @param null $unused
	 * @param OutputPage $output
	 * @param User $user
	 * @param WebRequest $request
	 * @param MediaWiki $mediaWiki
	 * @return bool|void
	 */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWiki ) {
		$output->addModules( 'ext.menuEditor.boostrap' );
		$this->menuFactory->initialize();
	}
}
