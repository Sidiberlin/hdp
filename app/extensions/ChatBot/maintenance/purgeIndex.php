<?php

use ChatBot\DeepsetApi\IndexApi;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;

$IP = dirname( dirname( dirname( __DIR__ ) ) );

require_once "$IP/maintenance/Maintenance.php";

class purgeIndex extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( "ChatBot" );

		$this->addOption( 'quick', 'Skip count down' );
	}

	public function execute() {
		if ( !$this->hasOption( 'quick' ) ) {
			$this->output( 'This will delete all indexes related to this wiki instance! Starting in ... ' );
			$this->countDown( 5 );
		}

		/** @var IndexApi $indexApi */
		$indexApi = MediaWikiServices::getInstance()->getService( 'DeepsetIndexApi' );
		try {
			$status = $indexApi->purgeIndex();
			if ( !$status->isGood() ) {
				$this->error( $status->getMessage()->text() );
			} else {
				$this->output( "Done." );
			}
		} catch ( Exception $e ) {
			$this->error( $e->getMessage() );
		}
	}
}

$maintClass = purgeIndex::class;
require_once RUN_MAINTENANCE_IF_MAIN;
