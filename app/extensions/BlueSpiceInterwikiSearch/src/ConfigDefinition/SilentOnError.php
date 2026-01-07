<?php

namespace BlueSpice\InterwikiSearch\ConfigDefinition;

use BlueSpice\ConfigDefinition\BooleanSetting;

class SilentOnError extends BooleanSetting {

	/**
	 * @return string[]
	 */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_SEARCH . '/BlueSpiceInterwikiSearch',
			static::MAIN_PATH_EXTENSION . '/BlueSpiceInterwikiSearch/' . static::FEATURE_SEARCH,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . '/BlueSpiceInterwikiSearch',
		];
	}

	/**
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'bs-interwikisearch-pref-silentonerror';
	}

}
