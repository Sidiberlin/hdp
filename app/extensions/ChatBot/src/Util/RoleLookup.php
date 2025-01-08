<?php

namespace ChatBot\Util;

use ChatBot\Extension;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;

class RoleLookup {

	/** @var array */
	private $groupCache = [];

	/** @var UserGroupManager */
	private $userGroupManager;

	/**
	 * @param UserGroupManager $userGroupManager
	 */
	public function __construct(
		UserGroupManager $userGroupManager
	) {
		$this->userGroupManager = $userGroupManager;
	}

	/**
	 *
	 * @param UserIdentity $user
	 *
	 * @return bool
	 */
	public function isMaintainer( UserIdentity $user ) {
		return $this->checkGroupMembership( $user, Extension::GROUP_MAINTAINER );
	}

	/**
	 *
	 * @param UserIdentity $user
	 *
	 * @return bool
	 */
	public function isBMBF( UserIdentity $user ) {
		return $this->checkGroupMembership( $user, Extension::GROUP_BMBF );
	}

	/**
	 *
	 * @param UserIdentity $user
	 *
	 * @return bool
	 */
	public function isProjectSponsor( UserIdentity $user ) {
		return $this->checkGroupMembership( $user, Extension::GROUP_PROJECT_SPONSOR );
	}

	/**
	 * @param UserIdentity $user
	 *
	 * @return bool
	 */
	public function isSysop( UserIdentity $user ): bool {
		return $this->checkGroupMembership( $user, 'sysop' );
	}

	/**
	 * @param UserIdentity $user
	 * @param string $group
	 *
	 * @return bool
	 */
	private function checkGroupMembership( UserIdentity $user, string $group ): bool {
		if ( !$user->isRegistered() ) {
			return false;
		}

		if ( !isset( $this->groupCache[$user->getId()] ) ) {
			$this->groupCache[$user->getId()] = $this->userGroupManager->getUserGroups( $user );
		}

		return in_array( $group, $this->groupCache[$user->getId()] );
	}
}
