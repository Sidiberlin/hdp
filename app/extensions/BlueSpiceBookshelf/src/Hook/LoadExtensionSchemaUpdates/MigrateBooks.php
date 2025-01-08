<?php

namespace BlueSpice\Bookshelf\Hook\LoadExtensionSchemaUpdates;

use FixBookChapterTitles;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use RebuildBooks;

class MigrateBooks implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addPostDatabaseUpdateMaintenance( RebuildBooks::class );
		$updater->addPostDatabaseUpdateMaintenance( FixBookChapterTitles::class );
	}
}
