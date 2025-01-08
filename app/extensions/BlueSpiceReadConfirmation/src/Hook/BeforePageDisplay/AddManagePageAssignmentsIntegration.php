<?php

namespace BlueSpice\ReadConfirmation\Hook\BeforePageDisplay;

use BlueSpice\Hook\BeforePageDisplay;

class AddManagePageAssignmentsIntegration extends BeforePageDisplay {

	/**
	 *
	 * @return bool
	 */
	protected function skipProcessing() {
		$title = $this->out->getTitle();
		if ( $title && !$title->isSpecial( 'ManagePageAssignments' ) ) {
			return true;
		}
		return false;
	}

	/**
	 *
	 * @return bool
	 */
	protected function doProcess() {
		$namespaces = isset( $GLOBALS['wgNamespacesWithEnabledReadConfirmation'] )
			? array_keys( $GLOBALS['wgNamespacesWithEnabledReadConfirmation'] )
			: [];

		$this->out->addJsConfigVars(
			'bsgReadConfirmationActivatedNamespaces',
			$namespaces
		);

		$this->out->addModuleStyles(
			'ext.readconfirmation.pageassignmentsintegration.styles'
		);
		$this->out->addModules( 'ext.readconfirmation.pageassignmentsintegration' );
		return true;
	}

}
