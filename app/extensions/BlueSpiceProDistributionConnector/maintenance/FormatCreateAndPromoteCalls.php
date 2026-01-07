<?php

use MediaWiki\Maintenance\Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class FormatCreateAndPromoteCalls extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'src', 'Path to the source file', true, true );
		$this->addOption( 'defaultPassword', 'Default password for all users', true, true );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$sourceFile = $this->getOption( 'src' );
		$defaultPassword = $this->getOption( 'defaultPassword' );
		$source = file_get_contents( $sourceFile );
		$lines = explode( "\n", $source );
		foreach ( $lines as $line ) {
			$trimmedLine = trim( $line );
			$parts = explode( "\t", $trimmedLine );
			if ( count( $parts ) !== 2 ) {
				continue;
			}
			$newName = trim( $parts[1] );

			$call = 'php maintenance/createAndPromote.php '
				. "\"$newName\" "
				. "$defaultPassword "
				. "\n";

			$this->output( $call );
		}
	}
}

$maintClass = 'FormatCreateAndPromoteCalls';
require_once RUN_MAINTENANCE_IF_MAIN;
