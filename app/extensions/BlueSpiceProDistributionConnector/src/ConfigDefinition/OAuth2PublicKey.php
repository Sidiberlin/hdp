<?php

namespace BlueSpice\ProDistributionConnector\ConfigDefinition;

use BlueSpice\ConfigDefinition;
use ExtensionRegistry;
use HTMLTextAreaField;

class OAuth2PublicKey extends ConfigDefinition\StringSetting implements ConfigDefinition\IOverwriteGlobal {

	/**
	 *
	 * @return HTMLTextAreaField
	 */
	public function getHtmlFormField() {
		return new HTMLTextAreaField( $this->makeFormFieldParams() + [ 'rows' => 10 ] );
	}

	/**
	 * @return string[]
	 */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_SYSTEM . '/BlueSpiceProDistributionConnector',
			static::MAIN_PATH_EXTENSION . '/BlueSpiceProDistributionConnector/' . static::FEATURE_SYSTEM ,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . '/BlueSpiceProDistributionConnector',
		];
	}

	/**
	 * @return string
	 */
	public function getLabelMessageKey() {
		return "bs-pro-distribution-config-outh-public-key";
	}

	/**
	 * @return bool
	 */
	public function isHidden() {
		return !ExtensionRegistry::getInstance()->isLoaded( 'OAuth' );
	}

	/**
	 * @return string
	 */
	public function getGlobalName() {
		return 'wgOAuth2PublicKey';
	}
}
