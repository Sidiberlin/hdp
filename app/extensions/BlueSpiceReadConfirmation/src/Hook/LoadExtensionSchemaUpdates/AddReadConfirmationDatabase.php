<?php

namespace BlueSpice\ReadConfirmation\Hook\LoadExtensionSchemaUpdates;

use BlueSpice\Hook\LoadExtensionSchemaUpdates;

class AddReadConfirmationDatabase extends LoadExtensionSchemaUpdates {
	/**
	 *
	 * @return bool
	 */
	protected function doProcess() {
		$dbType = $this->updater->getDB()->getType();
		$dir = $this->getExtensionPath();

		$this->updater->addExtensionTable(
			'bs_readconfirmation',
			"$dir/maintenance/db/sql/$dbType/bs_readconfirmation-generated.sql"
		);
		return true;
	}

	/**
	 *
	 * @return string
	 */
	protected function getExtensionPath() {
		return dirname( dirname( dirname( __DIR__ ) ) );
	}

}
