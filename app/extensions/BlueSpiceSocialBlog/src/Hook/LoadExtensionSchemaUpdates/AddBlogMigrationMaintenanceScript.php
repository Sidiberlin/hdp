<?php

namespace BlueSpice\Social\Blog\Hook\LoadExtensionSchemaUpdates;

use BlueSpice\Hook\LoadExtensionSchemaUpdates;

class AddBlogMigrationMaintenanceScript extends LoadExtensionSchemaUpdates {
	protected function doProcess() {
		$this->updater->addPostDatabaseUpdateMaintenance( \BSMigrateBlog::class );
		return true;
	}
}
