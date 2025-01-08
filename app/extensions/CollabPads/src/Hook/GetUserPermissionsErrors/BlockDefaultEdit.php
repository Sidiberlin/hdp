<?php

namespace MediaWiki\Extension\CollabPads\Hook\GetUserPermissionsErrors;

use MediaWiki\Extension\CollabPads\CollabSessionManager;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use Message;
use RequestContext;

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
			return true;
		}
		if ( $title->isSpecialPage() ) {
			return true;
		}

		$session = $this->collabSessionManager->getSession( $title->getNamespace(), $title->getText() );
		if ( $action === 'edit' && $session ) {
			$context = RequestContext::getMain();
			if ( $context->getActionName() === 'view' ) {
				// Don't block starting edit at all when viewing the page
				return true;
			}

			$request = $context->getRequest();

			$action = $request->getText( 'action', $request->getText( 'veaction', 'view' ) );

			if ( $action !== 'collab-edit' ) {
				// But block visual/source edit if there is a collab session running
				$result = Message::newFromKey( 'collabpads-normal-edit-blocked' );

				return false;
			}
		}

		return true;
	}
}
