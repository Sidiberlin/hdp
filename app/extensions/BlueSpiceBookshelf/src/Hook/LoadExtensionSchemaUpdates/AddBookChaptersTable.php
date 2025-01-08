<?php

namespace BlueSpice\Bookshelf\Hook\LoadExtensionSchemaUpdates;

use BlueSpice\Hook\LoadExtensionSchemaUpdates;

class AddBookChaptersTable extends LoadExtensionSchemaUpdates {

	protected function doProcess() {
		$dbType = $this->updater->getDB()->getType();
		$dir = $this->getExtensionPath();

		$this->updater->addExtensionTable(
			'bs_book_chapters',
			"$dir/maintenance/db/sql/$dbType/bs_book_chapters.sql"
		);

		$this->updater->dropExtensionField(
			'bs_book_chapters',
			'chapter_book_namespace',
			"$dir/maintenance/db/sql/$dbType/bs_book_chapters.patch.sql"
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
