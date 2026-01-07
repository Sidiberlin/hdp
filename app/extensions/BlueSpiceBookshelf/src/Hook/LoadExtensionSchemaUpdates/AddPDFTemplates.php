<?php

namespace BlueSpice\Bookshelf\Hook\LoadExtensionSchemaUpdates;

use AddBookPDFTemplates;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class AddPDFTemplates implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addPostDatabaseUpdateMaintenance( AddBookPDFTemplates::class );
	}
}
