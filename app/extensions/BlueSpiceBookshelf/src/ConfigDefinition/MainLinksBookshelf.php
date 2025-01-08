<?php

namespace BlueSpice\Bookshelf\ConfigDefinition;

use BlueSpice\ConfigDefinition\BooleanSetting;
use ExtensionRegistry;

class MainLinksBookshelf extends BooleanSetting {

	/**
	 * @return array
	 */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_SKINNING . '/BlueSpiceBookshelf',
			static::MAIN_PATH_EXTENSION . '/BlueSpiceBookshelf/' . static::FEATURE_SKINNING,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_FREE . '/BlueSpiceBookshelf',
		];
	}

	/**
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'bs-bookshelf-config-mainlinks-bookshelf-label';
	}

	/**
	 * @return string
	 */
	public function getHelpMessageKey() {
		return 'bs-bookshelf-config-mainlinks-bookshelf-help';
	}

	/**
	 * @return bool
	 */
	public function isHidden() {
		return !ExtensionRegistry::getInstance()->isLoaded( 'BlueSpiceDiscovery' );
	}

}
