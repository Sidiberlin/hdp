<?php

namespace ChatBot\Hook;

use ChatBot\Util\RoleAssignments;
use MediaWiki\Hook\SetupAfterCacheHook;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;

class SetUpPermissions implements SetupAfterCacheHook, GetUserPermissionsErrorsHook {

	/**
	 * @inheritDoc
	 */
	public function onSetupAfterCache() {
		// Assign roles to custom groups - this is called after BSPM data is loaded from DB (overwriting it)
		( new RoleAssignments() )->apply();
	}

	/**
	 * @inheritDoc
	 */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
	}
}
