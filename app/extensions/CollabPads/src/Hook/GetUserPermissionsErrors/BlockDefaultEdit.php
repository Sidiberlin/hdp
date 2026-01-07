<?php

namespace MediaWiki\Extension\CollabPads\Hook\GetUserPermissionsErrors;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CollabPads\CollabSessionManager;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;

class BlockDefaultEdit implements GetUserPermissionsErrorsHook {

	/**
	 * @var CollabSessionManager
	 */
	private $collabSessionManager;

	/**
	 * @param CollabSessionManager $collabSessionManager
	 */
	public function __construct( CollabSessionManager $collabSessionManager ) {
		$this->collabSessionManager = $collabSessionManager;
	}

	/**
	 * @inheritDoc
	 */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( defined( 'MEDIAWIKI_INSTALL' ) || defined( 'MW_UPDATER' ) ) {
			return;
		}

		if (
			$action !== 'edit' ||
			!$title->exists() ||
			!$title->isContentPage()
		) {
			return;
		}

		$context = RequestContext::getMain();
		if ( $context->getActionName() === 'view' ) {
			// Don't block edit from page view
			return;
		}

		$request = $context->getRequest();
		$requestAction = $request->getText( 'action', $request->getText( 'veaction', 'view' ) );
		if ( $requestAction === 'collab-edit' ) {
			return;
		}

		$session = $this->collabSessionManager->getSession( $title->getNamespace(), $title->getText() );
		if ( $session ) {
			// Block visual/source edit if there is a collab session running
			$result = Message::newFromKey( 'collabpads-normal-edit-blocked' );

			return false;
		}
	}
}
