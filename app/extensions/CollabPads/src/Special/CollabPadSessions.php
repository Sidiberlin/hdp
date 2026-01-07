<?php

namespace MediaWiki\Extension\CollabPads\Special;

use MediaWiki\Html\Html;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPage;

class CollabPadSessions extends SpecialPage {

	/** @var PermissionManager */
	private $permissionManager;

	/**
	 * @param PermissionManager $permissionManager
	 */
	public function __construct( PermissionManager $permissionManager ) {
		$this->permissionManager = $permissionManager;
		parent::__construct( 'CollabPadSessions', '', false );
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->requireLogin();
		$this->checkPermissions();

		if ( $this->permissionManager->userHasRight( $this->getUser(), 'collabpadsessions-admin' ) ) {
			$this->getOutput()->setPageTitle( $this->msg( 'collabpadsessions-admin' )->text() );
			$this->getOutput()->addModules( $this->getModules() );
			$this->getOutput()->addHTML( Html::element( 'div', [ 'id' => 'collabpadsessions-admin-grid' ] ) );
		} else {
			$this->getOutput()->addModules( $this->getModules() );
			$this->getOutput()->addHTML( Html::element( 'div', [ 'id' => 'collabpadsessions-grid' ] ) );
		}
	}

	/**
	 * @return string ID of the HTML element being added
	 */
	protected function getId() {
		return 'collabpadsessions-grid';
	}

	/**
	 * @return array
	 */
	protected function getModules() {
		return [
			"ext.collabpads.sessions"
		];
	}
}
