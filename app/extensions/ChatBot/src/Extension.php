<?php

namespace ChatBot;

use ChatBot\Util\RoleAssignments;

class Extension {
	public const GROUP_BMBF = 'Ministerium';
	public const GROUP_PROJECT_SPONSOR = 'Projektträger';

	public const GROUP_MAINTAINER = 'Maintainer';

	/**
	 * @return void
	 */
	public static function onRegistration() {
		$GLOBALS['wgGroupPermissions'][self::GROUP_BMBF]['read'] = true;
		$GLOBALS['wgGroupPermissions'][self::GROUP_PROJECT_SPONSOR]['read'] = true;
		$GLOBALS['wgGroupPermissions'][self::GROUP_MAINTAINER]['read'] = true;

		$GLOBALS['wgAdditionalGroups'][self::GROUP_BMBF] = '';
		$GLOBALS['wgAdditionalGroups'][self::GROUP_PROJECT_SPONSOR] = '';
		$GLOBALS['wgAdditionalGroups'][self::GROUP_MAINTAINER] = '';

		$GLOBALS['wgGroupTypes'][self::GROUP_BMBF] = 'custom';
		$GLOBALS['wgGroupTypes'][self::GROUP_PROJECT_SPONSOR] = 'custom';
		$GLOBALS['wgGroupTypes'][self::GROUP_MAINTAINER] = 'custom';

		// Assign roles to custom groups - this is called before BSPM data is loaded from DB
		// - sets defaults in new setups
		( new RoleAssignments() )->apply();
	}
}
