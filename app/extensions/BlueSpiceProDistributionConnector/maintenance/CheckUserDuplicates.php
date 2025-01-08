<?php
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class CheckUserDuplicates extends Maintenance {

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$usernameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
		$dbr = $this->getDB( DB_REPLICA );
		$res = $dbr->select( 'user', 'user_name' );

		$userMap = [];
		foreach ( $res as $row ) {
			$username = $row->user_name;
			$normalUsername = ucfirst( strtolower( $username ) );
			if ( !$usernameUtils->isUsable( $username ) ) {
				continue;
			}
			if ( !$usernameUtils->isUsable( $normalUsername ) ) {
				continue;
			}
			if ( $username === $normalUsername ) {
				continue;
			}
			$userMap[$username] = $normalUsername;
		}

		if ( count( $userMap ) === 0 ) {
			$this->error( "No duplicate users found\n" );
			return;
		}
		foreach ( $userMap as $username => $normalUsername ) {
			$this->output( "$username\t$normalUsername\n" );
		}
	}
}

$maintClass = 'checkUserDuplicates';
require_once RUN_MAINTENANCE_IF_MAIN;
