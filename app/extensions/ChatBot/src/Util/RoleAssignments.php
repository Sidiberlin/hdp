<?php

namespace ChatBot\Util;

use ChatBot\Extension;

class RoleAssignments {

	/**
	 * This is called twice:
	 * - on registration - ensure to apply assignments for setups where there are no
	 * assignments stored in DB yet (before DB data is loaded)
	 * - setupAfterCache - ensure to apply assignments for setups where
	 * there are assignments stored in DB (after DB data is loaded)
	 *
	 * @return void
	 */
	public function apply() {
		$GLOBALS['bsgGroupRoles']['user']['reader'] = true;
		$GLOBALS['bsgGroupRoles'][Extension::GROUP_MAINTAINER]['editor'] = true;

		$GLOBALS['bsgNamespaceRolesLockdown'][NS_BMBF]['reader'] = [
			Extension::GROUP_BMBF,
			Extension::GROUP_MAINTAINER,
			'sysop'
		];

		$GLOBALS['bsgNamespaceRolesLockdown'][NS_BMBF_TALK]['reader'] = [
			Extension::GROUP_BMBF,
			Extension::GROUP_MAINTAINER,
			'sysop'
		];

		$GLOBALS['bsgNamespaceRolesLockdown'][NS_PT]['reader'] = [
			Extension::GROUP_PROJECT_SPONSOR,
			Extension::GROUP_MAINTAINER,
			'sysop'
		];

		$GLOBALS['bsgNamespaceRolesLockdown'][NS_PT_TALK]['reader'] = [
			Extension::GROUP_PROJECT_SPONSOR,
			Extension::GROUP_MAINTAINER,
			'sysop'
		];
	}
}
