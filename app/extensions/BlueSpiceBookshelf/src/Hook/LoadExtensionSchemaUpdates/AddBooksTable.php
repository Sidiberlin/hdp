<?php

namespace BlueSpice\Bookshelf\Hook\LoadExtensionSchemaUpdates;

use BlueSpice\Hook\LoadExtensionSchemaUpdates;

class AddBooksTable extends LoadExtensionSchemaUpdates {

	protected function doProcess() {
		$dbType = $this->updater->getDB()->getType();
		$dir = $this->getExtensionPath();

		$this->updater->addExtensionTable(
			'bs_books',
			"$dir/maintenance/db/sql/$dbType/bs_books.sql"
		);

		$this->updater->addExtensionField(
			'bs_books',
			'book_name',
			"$dir/maintenance/db/sql/$dbType/bs_books.patch.sql"
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
