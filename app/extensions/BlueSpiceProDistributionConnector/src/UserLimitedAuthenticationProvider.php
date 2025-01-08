<?php

namespace BlueSpice\ProDistributionConnector;

use MediaWiki\Auth\AbstractPreAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\MediaWikiServices;
use StatusValue;
use User;

class UserLimitedAuthenticationProvider extends AbstractPreAuthenticationProvider {
	/**
	 * Determine whether an account creation may begin
	 *
	 * Called from AuthManager::beginAccountCreation()
	 *
	 * @note No need to test if the account exists, AuthManager checks that
	 * @param User $user User being created (not added to the database yet).
	 *   This may become a "UserValue" in the future, or User may be refactored
	 *   into such.
	 * @param User $creator User doing the creation. This may become a
	 *   "UserValue" in the future, or User may be refactored into such.
	 * @param AuthenticationRequest[] $reqs
	 * @return StatusValue
	 */
	public function testForAccountCreation( $user, $creator, array $reqs ) {
		$userCounter = MediaWikiServices::getInstance()->getService( 'BlueSpiceUserCounter' );
		$userLimit = $userCounter->getUserLimit();

		if ( $userLimit === -1 ) {
			// unlimited
			return StatusValue::newGood();
		}

		$currentNumOfUser = $userCounter->getCurrentNumberOfUser();
		if ( $currentNumOfUser < $userLimit ) {
			return StatusValue::newGood();
		}

		return StatusValue::newFatal( 'bs-pro-distribution-createaccount-fail-max-accounts-exceed' );
	}
}
