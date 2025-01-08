<?php

namespace BlueSpice\ReadConfirmation\Hook\BeforePageDisplay;

use BlueSpice\Hook\BeforePageDisplay;

class AddResources extends BeforePageDisplay {

	/**
	 *
	 * @return bool
	 */
	protected function skipProcessing() {
		global $wgNamespacesWithEnabledReadConfirmation;

		$namespaces = isset( $wgNamespacesWithEnabledReadConfirmation )
			? array_keys( $GLOBALS['wgNamespacesWithEnabledReadConfirmation'] )
			: [];

		$title = $this->out->getTitle();
		$namespace = $title->getNamespace();

		if ( !in_array( $namespace, $namespaces ) ) {
			if ( $title->isSpecial( 'ManagePageAssignments' ) ) {
				return false;
			}
			return true;
		}
		return false;
	}

	protected function doProcess() {
		$this->out->addModuleStyles( 'ext.readconfirmation.styles' );
		$this->out->addModules( 'ext.readconfirmation.scripts' );

		$isAllowed = false;
		$user = $this->skin->getUser();
		$permissionManager = $this->getServices()->getPermissionManager();
		if ( $user &&
			$permissionManager->userHasRight( $user, 'readconfirmationviewconfirmations' )
		) {
			$isAllowed = true;
		}

		$this->out->addJsConfigVars( 'bsReadConfirmationsViewRight', $isAllowed );

		return true;
	}

}
