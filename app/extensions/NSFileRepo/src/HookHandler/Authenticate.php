<?php

namespace MediaWiki\Extension\NSFileRepo\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Config\MultiConfig;
use MediaWiki\Extension\NSFileRepo\Config as NSFileRepoConfig;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;

class Authenticate implements GetUserPermissionsErrorsHook {

	/**
	 * @var PermissionManager
	 */
	private $permissionManager;

	/**
	 * @var MultiConfig
	 */
	private $config;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * @param PermissionManager $permissionManager
	 * @param Config $mainConfig
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		PermissionManager $permissionManager, Config $mainConfig, TitleFactory $titleFactory
	) {
		$this->permissionManager = $permissionManager;
		$this->titleFactory = $titleFactory;
		$this->config = new MultiConfig( [
			new NSFileRepoConfig(),
			$mainConfig
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		$whitelistRead = $this->config->get( 'WhitelistRead' );
		if ( $whitelistRead !== false && in_array( $title->getPrefixedText(), $whitelistRead ) ) {
			return true;
		}

		if ( $title->getNamespace() !== NS_FILE ) {
			return true;
		}

		$ntitle = $this->titleFactory->newFromText( $title->getText() );

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
			$permissionStatus = $this->permissionManager->getPermissionStatus(
				$action,
				$user,
				$ntitle
			);
			if ( !empty( $permissionStatus->getMessages() ) ) {
				$result = $permissionStatus->getMessages();
				return false;
			}
		}

		return true;
	}
}
