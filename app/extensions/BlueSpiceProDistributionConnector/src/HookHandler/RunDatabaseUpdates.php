<?php

namespace BlueSpice\ProDistributionConnector\HookHandler;

use BlueSpice\ProDistributionConnector\Maintenance\MigrateSocialCommentsToCommentStreams;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class RunDatabaseUpdates implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addPostDatabaseUpdateMaintenance(
			MigrateSocialCommentsToCommentStreams::class
		);
	}
}
