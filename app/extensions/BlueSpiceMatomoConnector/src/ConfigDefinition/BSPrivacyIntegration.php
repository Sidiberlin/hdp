<?php

namespace BlueSpice\MatomoConnector\ConfigDefinition;

use BlueSpice\ConfigDefinition\BooleanSetting;

class BSPrivacyIntegration extends BooleanSetting {

	/** @inheritDoc */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_DATA_ANALYSIS . '/BlueSpiceMatomoConnector',
			static::MAIN_PATH_EXTENSION . '/BlueSpiceMatomoConnector/' . static::FEATURE_DATA_ANALYSIS,
			static::MAIN_PATH_PACKAGE . '/MatomoConnect/BlueSpiceMatomoConnector',
		];
	}

	/** @inheritDoc */
	public function getLabelMessageKey() {
		return 'bs-matomoconnector-pref-bsprivacyintegration';
	}

	/** @inheritDoc */
	public function getHelpMessageKey() {
		return 'bs-matomoconnector-pref-bsprivacyintegration-help';
	}

}
