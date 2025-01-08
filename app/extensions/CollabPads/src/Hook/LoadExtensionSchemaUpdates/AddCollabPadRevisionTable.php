<?php

namespace MediaWiki\Extension\CollabPads\Hook\LoadExtensionSchemaUpdates;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class AddCollabPadRevisionTable implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dbType = $updater->getDB()->getType();
		$dir = "{$this->getExtensionPath()}/maintenance/db";

		$updater->addExtensionTable(
			'collabpad_revision',
			"$dir/sql/$dbType/collabpad_revision-generated.sql"
		);
	}

	/**
	 *
	 * @return string
	 */
	protected function getExtensionPath() {
		return dirname( dirname( dirname( __DIR__ ) ) );
	}
}
