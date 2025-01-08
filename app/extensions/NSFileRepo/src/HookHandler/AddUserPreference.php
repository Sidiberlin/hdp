<?php

namespace MediaWiki\Extension\NSFileRepo\HookHandler;

use MediaWiki\Preferences\Hook\GetPreferencesHook;

class AddUserPreference implements GetPreferencesHook {

	/**
	 *
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$api = [ 'type' => 'api' ];
		$preferences[ 'filelist-show-namespace_text' ] = $api;
	}
}
