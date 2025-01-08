<?php
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class MergeUserBatch extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'src', 'Path to the source file', true, true );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$mergeUser = User::newSystemUser( 'Maintenance script', [ 'steal' => true ] );
		$sourceFile = $this->getOption( 'src' );
		$source = file_get_contents( $sourceFile );
		$lines = explode( "\n", $source );
		foreach ( $lines as $line ) {
			$trimmedLine = trim( $line );
			$parts = explode( "\t", $trimmedLine );
			if ( count( $parts ) !== 2 ) {
				continue;
			}
			$oldName = trim( $parts[0] );
			$newName = trim( $parts[1] );

			$oldUser = $userFactory->newFromName( $oldName );
			$newUser = $userFactory->newFromName( $newName );

			if ( !$oldUser instanceof User || !$newUser instanceof User ) {
				$this->error( "Invalid merge: $oldName -> $newName\n" );
				continue;
			}

			try {
				// This code is basically copied from Special:MergeUser implemented in Extension:UserMerge
				$um = new MergeUser( $oldUser, $newUser, new UserMergeLogger() );
				$um->merge( $mergeUser, __METHOD__ );
				$failed = $um->delete( $mergeUser, 'wfMessage' );
				if ( $failed ) {
					$this->error( "Failed to delete $oldName\n" );
				}
			} catch ( Exception $e ) {
				$this->error( "Error: {$e->getMessage()}\n" );
				continue;
			}

		}
	}
}

$maintClass = 'MergeUserBatch';
require_once RUN_MAINTENANCE_IF_MAIN;
