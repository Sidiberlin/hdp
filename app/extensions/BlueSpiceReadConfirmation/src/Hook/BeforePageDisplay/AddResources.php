<?php

namespace BlueSpice\ReadConfirmation\Hook\BeforePageDisplay;

use BlueSpice\ReadConfirmation\Mechanism\NonMinorEdit;
use BlueSpice\ReadConfirmation\MechanismFactory;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;

class AddResources implements BeforePageDisplayHook {

	/** @var RevisionLookup */
	protected $revisionLookup;

	/** @var MechanismFactory */
	protected $mechanismFactory;

	/** @var PermissionManager */
	protected $permissionManager;

	/**
	 * @param RevisionLookup $revisionLookup
	 * @param MechanismFactory $mechanismFactory
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		RevisionLookup $revisionLookup, MechanismFactory $mechanismFactory, PermissionManager $permissionManager
	) {
		$this->revisionLookup = $revisionLookup;
		$this->mechanismFactory = $mechanismFactory;
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @param OutputPage $out
	 * @return bool
	 */
	protected function shouldSkipProcessing( OutputPage $out ) {
		global $wgNamespacesWithEnabledReadConfirmation;

		$namespaces = isset( $wgNamespacesWithEnabledReadConfirmation )
			? array_keys( $GLOBALS['wgNamespacesWithEnabledReadConfirmation'] )
			: [];

		$title = $out->getTitle();
		if ( !$title ) {
			return true;
		}
		$action = $out->getRequest()->getVal( 'veaction', $out->getRequest()->getVal( 'action', 'view' ) );
		if ( $action !== 'view' ) {
			return true;
		}

		$namespace = $title->getNamespace();
		if ( !in_array( $namespace, $namespaces ) ) {
			if ( $title->isSpecial( 'ManagePageAssignments' ) ) {
				return false;
			}
			return true;
		}
		$type = $out->getRequest()->getVal( 'type', '' );
		$diff = $out->getRequest()->getVal( 'diff', '' );
		if ( is_numeric( $diff ) ) {
			$diff = (int)$diff;
		}

		$isDiffView = $diff && $type === 'revision';

		$isRevisionCurrent = $out->isRevisionCurrent();
		if ( !$isRevisionCurrent && !$isDiffView ) {
			return true;
		}

		/** @var NonMinorEdit */
		$mechanism = $this->mechanismFactory->getMechanismInstance();
		$toRead = $mechanism->getLatestRevisionToConfirm( $title, $out->getUser() );
		if ( !$toRead ) {
			return true;
		}

		$pageId = $title->getArticleID();
		$userId = $out->getUser()->getId();
		$confirmations = $mechanism->getCurrentReadConfirmations( [ $userId ], [ $pageId ] );
		$hasRead = !empty( $confirmations[$pageId] );
		if ( $hasRead ) {
			return true;
		}

		if ( $isDiffView ) {
			// In diff view
			$oldId = $out->getRequest()->getInt( 'oldid' );
			$latestRead = $mechanism->getLatestReadConfirmations( [ $userId ] );
			if ( !isset( $latestRead[$userId][$pageId] ) ) {
				$firstRevision = $this->revisionLookup->getFirstRevision( $title );
				if ( !$firstRevision ) {
					return true;
				}
				// User read nothing, if not comparing to current must read, skip
				return $toRead->getId() !== $diff || $oldId !== $firstRevision->getId();
			}
			$userLastRead = $latestRead[$userId][$pageId];
			if ( $diff !== $userLastRead['latest_rev'] || $oldId !== $userLastRead['latest_read_rev'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $this->shouldSkipProcessing( $out ) ) {
			return;
		}

		$out->addModuleStyles( 'ext.readconfirmation.styles' );
		$out->addModules( 'ext.readconfirmation.scripts' );

		$user = $skin->getUser();
		$isAllowed = $this->permissionManager->userHasRight( $user, 'readconfirmationviewconfirmations' );

		$out->addJsConfigVars( 'bsReadConfirmationsViewRight', $isAllowed );
	}

}
