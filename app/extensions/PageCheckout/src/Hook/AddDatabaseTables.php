<?php

namespace MediaWiki\Extension\PageCheckout\Hook;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class AddDatabaseTables implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dbType = $updater->getDB()->getType();
		$base = dirname( __DIR__, 2 );

		$updater->addExtensionTable(
			'page_checkout_event',
			"$base/db/$dbType/page_checkout_event.sql"
		);
		$updater->addExtensionTable(
			'page_checkout_locks',
			"$base/db/$dbType/page_checkout_locks.sql"
		);

		$updater->addExtensionIndex(
			'page_checkout_locks',
			'pcl_page_id_user_id_index',
			"$base/db/$dbType/page_checkout_locks_index.sql"
		);

		return true;
	}
}
