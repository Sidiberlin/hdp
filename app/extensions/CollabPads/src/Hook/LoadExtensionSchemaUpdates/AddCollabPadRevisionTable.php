<?php

namespace MediaWiki\Extension\CollabPads\Hook\LoadExtensionSchemaUpdates;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class AddCollabPadRevisionTable implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dbType = $updater->getDB()->getType();
		$dir = dirname( __DIR__, 3 );

		$updater->addExtensionTable(
			'collabpad_revision',
			"$dir/maintenance/db/$dbType/collabpad_revision.sql"
		);
	}
}
