<?php

namespace MediaWiki\Extension\CollabPads\Hook\LoadExtensionSchemaUpdates;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class AddCollabPadAclTokenTable implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dbType = $updater->getDB()->getType();
		$dir = "{$this->getExtensionPath()}/maintenance/db";

		$updater->addExtensionTable(
			'collabpad_acl_token',
			"$dir/sql/$dbType/collabpad_acl_token.sql"
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
