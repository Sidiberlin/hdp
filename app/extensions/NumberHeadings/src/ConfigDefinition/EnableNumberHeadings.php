<?php

namespace MediaWiki\Extension\NumberHeadings\ConfigDefinition;

use BlueSpice\ConfigDefinition\BooleanSetting;
use BlueSpice\ConfigDefinition\IOverwriteGlobal;

class EnableNumberHeadings extends BooleanSetting implements IOverwriteGlobal {

	/**
	 *
	 * @return string[]
	 */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_CONTENT_STRUCTURING . "/NumberHeadings",
			static::MAIN_PATH_EXTENSION . "/NumberHeadings/" . static::FEATURE_CONTENT_STRUCTURING,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . "/NumberHeadings",
		];
	}

	/**
	 *
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'numberheadings-conf-enable-numbering';
	}

	/**
	 *
	 * @return string
	 */
	public function getGlobalName() {
		return "wgNumberHeadingsEnable";
	}

	/**
	 *
	 * @return string
	 */
	public function getHelpMessageKey() {
		return 'numberheadings-conf-enable-numbering-help';
	}
}
