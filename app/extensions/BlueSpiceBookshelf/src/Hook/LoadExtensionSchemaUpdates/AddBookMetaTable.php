<?php

namespace BlueSpice\Bookshelf\Hook\LoadExtensionSchemaUpdates;

use BlueSpice\Hook\LoadExtensionSchemaUpdates;

class AddBookMetaTable extends LoadExtensionSchemaUpdates {

	protected function doProcess() {
		$dbType = $this->updater->getDB()->getType();
		$dir = $this->getExtensionPath();

		$this->updater->addExtensionTable(
			'bs_book_meta',
			"$dir/maintenance/db/sql/$dbType/bs_book_meta.sql"
		);
	}

	/**
	 *
	 * @return string
	 */
	protected function getExtensionPath() {
		return dirname( __DIR__, 3 );
	}

}
