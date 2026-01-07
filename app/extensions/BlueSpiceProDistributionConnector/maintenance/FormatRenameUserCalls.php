<?php

use MediaWiki\Maintenance\Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class FormatRenameUserCalls extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'src', 'Path to the source file', true, true );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
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

			$call = 'php extensions/Renameuser/maintenance/renameUser.php '
				. "--oldname \"$oldName\" "
				. "--newname \"$newName\" "
				. '--performer "Maintenance script" '
				. '--reason "Rename user to match new naming convention"'
				. "\n";

			$this->output( $call );
		}
	}
}

$maintClass = 'FormatRenameUserCalls';
require_once RUN_MAINTENANCE_IF_MAIN;
