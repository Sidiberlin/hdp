<?php

namespace BlueSpice\Bookshelf\ConfigDefinition;

use BlueSpice\ConfigDefinition\StringSetting;
use MediaWiki\Registration\ExtensionRegistry;

class DefaultBookPDFTemplate extends StringSetting implements \BlueSpice\Bookshelf\ISettingPaths {

	/**
	 *
	 * @return array
	 */
	public function getPaths() {
		$feature = static::FEATURE_EXPORT;
		$ext = 'BlueSpiceBookshelf';
		$package = static::PACKAGE_PRO;
		return [
			static::MAIN_PATH_FEATURE . "/$feature/$ext",
			static::MAIN_PATH_EXTENSION . "/$ext/$feature",
			static::MAIN_PATH_PACKAGE . "/$package/$ext",
		];
	}

	/**
	 *
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'bs-bookshelf-config-default-book-pdf-template';
	}

	/**
	 *
	 * @return string
	 */
	public function getHelpMessageKey() {
		return 'bs-bookshelf-config-default-book-pdf-template-help';
	}

	/**
	 * @return bool
	 */
	public function isHidden() {
		return !ExtensionRegistry::getInstance()->isLoaded( 'PDFCreator' );
	}
}
