<?php

namespace ChatBot\ConfigDefinition;

use BlueSpice\ConfigDefinition\ArraySetting;
use BlueSpice\ConfigDefinition\IOverwriteGlobal;

class ProblemSelection extends ArraySetting implements IOverwriteGlobal {

	/**
	 *
	 * @inheritDoc
	 */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_SYSTEM . '/ChatBot',
			static::MAIN_PATH_EXTENSION . '/ChatBot/' . static::FEATURE_SYSTEM,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_CUSTOMIZING . '/ChatBot'
		];
	}

	/**
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'chat-config-feedback-problem';
	}

	/**
	 *
	 * @return string
	 */
	public function getGlobalName() {
		return 'wgBmbfFeedbackAnswerProblemSelection';
	}

	/**
	 *
	 * @return \HTMLMultiSelectPlusAdd
	 */
	public function getHtmlFormField() {
		return new \HTMLMultiSelectPlusAdd( $this->makeFormFieldParams() );
	}

}
