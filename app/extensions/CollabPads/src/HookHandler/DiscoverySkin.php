<?php

namespace MediaWiki\Extension\CollabPads\HookHandler;

use BlueSpice\Discovery\Hook\BlueSpiceDiscoveryTemplateDataProviderAfterInit;
use BlueSpice\Discovery\ITemplateDataProvider;
use MediaWiki\Extension\CollabPads\CollabSessionManager;
use MediaWiki\HookContainer\HookContainer;
use RequestContext;
use Title;

class DiscoverySkin implements BlueSpiceDiscoveryTemplateDataProviderAfterInit {

	/** @var CollabSessionManager */
	private $collabSessionManager;

	/** @var HookContainer */
	private $hookContainer;

	/**
	 * @param CollabSessionManager $collabSessionManager
	 * @param HookContainer $hookContainer
	 */
	public function __construct( CollabSessionManager $collabSessionManager, HookContainer $hookContainer ) {
		$this->collabSessionManager = $collabSessionManager;
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @param ITemplateDataProvider $registry
	 * @return void
	 */
	public function onBlueSpiceDiscoveryTemplateDataProviderAfterInit( $registry ): void {
		$title = RequestContext::getMain()->getTitle();
		if ( !$title || !$title->isContentPage() ) {
			return;
		}

		$registry->register( 'panel/edit', 'ca-collabpad' );

		$session = $this->collabSessionManager->getSessionExistsByName(	$title->getNamespace(),	$title->getDBkey() );
		if ( $session ) {
			// Disable other edit actions when CollabPad session is active
			$registry->unregister( 'panel/edit', 'ca-new-section' );
			$registry->unregister( 'panel/edit', 'ca-ve-edit' );
			$registry->unregister( 'panel/edit', 'ca-edit' );
			return;
		}

		$this->maybeDisableContentAction( $title, $registry );
	}

	/**
	 * Allow extensions to unregister 'ca-collabpad'
	 *
	 * @param Title $title
	 * @param ITemplateDataProvider $registry
	 */
	protected function maybeDisableContentAction( Title $title, ITemplateDataProvider $registry ): void {
		$this->hookContainer->run(
			'CollabPadsAfterAddContentAction',
			[ $title, $registry ]
		);
	}
}
