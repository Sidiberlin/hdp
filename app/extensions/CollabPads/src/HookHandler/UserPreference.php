<?php

namespace MediaWiki\Extension\CollabPads\HookHandler;

use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\User;

class UserPreference implements GetPreferencesHook {

	/**
	 * @param User $user
	 * @param array &$defaultPreferences
	 */
	public function onGetPreferences( $user, &$defaultPreferences ) {
		$defaultPreferences['collabPads-startSessionDialog-dontShowAgain'] = [
			'type' => 'api',
			'default' => '0',
		];
	}
}
