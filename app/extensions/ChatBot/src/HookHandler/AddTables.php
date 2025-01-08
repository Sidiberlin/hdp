<?php

namespace ChatBot\HookHandler;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class AddTables implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable(
			'bmbf_index_pages',
			__DIR__ . '/../../db/bmbf_index_pages.sql'
		);
	}
}
