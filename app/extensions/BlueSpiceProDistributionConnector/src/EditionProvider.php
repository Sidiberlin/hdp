<?php

namespace BlueSpice\ProDistributionConnector;

class EditionProvider {

	/**
	 * @return string|null if no edition info can be found
	 */
	public function getEdition(): ?string {
		$file = $GLOBALS['IP'] . '/BLUESPICE-EDITION';
		if ( !file_exists( $file ) ) {
			return null;
		}
		return strtolower( trim( file_get_contents( $file ) ) );
	}

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
