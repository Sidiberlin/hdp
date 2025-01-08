<?php

use MediaWiki\MediaWikiServices;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class RefreshTasks extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Refreshes the simple_tasks table' );
	}

	public function execute() {
		$manager = MediaWikiServices::getInstance()->getService( 'SimpleTaskManager' );
		$manager->refreshAll( $this );
	}

	/**
	 * @param string $text
	 */
	public function outputText( $text ) {
		$this->output( $text );
	}
}

$maintClass = RefreshTasks::class;
require_once RUN_MAINTENANCE_IF_MAIN;
