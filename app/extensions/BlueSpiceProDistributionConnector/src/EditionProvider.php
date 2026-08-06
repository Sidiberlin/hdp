<?php

namespace BlueSpice\ProDistributionConnector;

use BlueSpice\DistributionConnector\EditionProvider as BaseEditionProvider;

class EditionProvider extends BaseEditionProvider {

	/**
	 * Check whether edition requires a license
	 *
	 * @return bool
	 */
	public function checkRequiresLicense(): bool {
		$edition = $this->getEdition();
		if ( !$edition ) {
			return true;
		}
		$licensableEditions = [ 'pro', 'cloud', ];
		return in_array( $edition, $licensableEditions );
	}

	/**
	 * @return bool
	 */
	public function checkIsLicenseConfigurable(): bool {
		return $this->checkRequiresLicense() && $this->getEdition() !== 'cloud';
	}
}
