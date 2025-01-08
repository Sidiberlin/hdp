<?php

namespace MediaWiki\Extension\NSFileRepo\HookHandler;

use Config;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\Permissions\PermissionManager;
use MultiConfig;
use NSFileRepo\Config as NSFileRepoConfig;
use Title;
use User;

class PermissionChecker implements GetUserPermissionsErrorsHook {

	/**
	 *
	 * @var Config
	 */
	private $config = null;

	/**
	 *
	 * @var PermissionManager
	 */
	private $permManager = null;

	/**
	 *
	 * @param Config $mainConfig
	 * @param PermissionManager $permissionManager
	 */
	public function __construct( $mainConfig, $permissionManager ) {
		$this->config = new MultiConfig( [
			new NSFileRepoConfig(),
			$mainConfig
		] );
		$this->permManager = $permissionManager;
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param string &$result
	 * @return bool|void
	 */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		$whitelistRead = $this->config->get( 'WhitelistRead' );
		if ( $whitelistRead !== false && in_array( $title->getPrefixedText(), $whitelistRead ) ) {
			return true;
		}

		if ( $title->getNamespace() !== NS_FILE ) {
			return true;
		}

		$ntitle = Title::newFromText( $title->getDBkey() );

		// When image title cannot be created, due to upload errors,
		//$title->getDBKey() is empty, resulting in an invaid
		//title object in Title::newFromText
		if ( !$ntitle instanceof Title ) {
			return true;
		}

		// Additional check for NS_MAIN: If a user is not allowed to read NS_MAIN he should also be not allowed
		//to view files with no namespace-prefix as they are logically assigned to namespace NS_MAIN
		$titleIsNSMAIN = $ntitle->getNamespace() === NS_MAIN;
		$titleNSaboveThreshold = $ntitle->getNamespace() >= $this->config->get( 'NamespaceThreshold' );
		if ( $titleIsNSMAIN || $titleNSaboveThreshold ) {
			$errors = $this->permManager->getPermissionErrors(
				$action,
				$user,
				$ntitle
			);
			if ( !empty( $errors ) ) {
				$result = $errors;
				return false;
			}
		}

		return true;
	}
}
