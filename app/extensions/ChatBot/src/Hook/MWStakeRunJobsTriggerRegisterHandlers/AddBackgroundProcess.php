<?php

namespace ChatBot\Hook\MWStakeRunJobsTriggerRegisterHandlers;

use ChatBot\RunJobsTriggerHandler\IndexDeepset;

class AddBackgroundProcess {

	/**
	 *
	 * @param array &$handlers
	 *
	 * @return bool
	 */
	public static function callback( &$handlers ) {
		$handlers[IndexDeepset::HANDLER_KEY] = [
			'class' => IndexDeepset::class,
			'services' => [
				'DeepsetIndexApi',
				'DBLoadBalancer',
				'TitleFactory',
				'WikiPageFactory',
				'ParserFactory'
			]
		];

		return true;
	}
}
