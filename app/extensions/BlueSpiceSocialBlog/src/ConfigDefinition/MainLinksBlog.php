<?php

namespace BlueSpice\Social\Blog\ConfigDefinition;

use BlueSpice\ConfigDefinition\BooleanSetting;
use ExtensionRegistry;

class MainLinksBlog extends BooleanSetting {

	/**
	 * @return array
	 */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_SKINNING . '/BlueSpiceSocialBlog',
			static::MAIN_PATH_EXTENSION . '/BlueSpiceSocialBlog/' . static::FEATURE_SKINNING,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_FREE . '/BlueSpiceSocialBlog',
		];
	}

	/**
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'bs-socialblog-config-mainlinks-blog-label';
	}

	/**
	 * @return string
	 */
	public function getHelpMessageKey() {
		return 'bs-socialblog-config-mainlinks-blog-help';
	}

	/**
	 * @return bool
	 */
	public function isHidden() {
		return !ExtensionRegistry::getInstance()->isLoaded( 'BlueSpiceDiscovery' );
	}

}
