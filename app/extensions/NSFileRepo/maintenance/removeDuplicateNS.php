<?php

use MediaWiki\Maintenance\Maintenance;

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

/**
 * If in table oldimage oi_archive_name contains
 * multiple NS prefixes, this script fixes it
 */
class RemoveDuplicateNS extends Maintenance {

	public function __construct() {
		parent::__construct();
		// Remove the default illegal char ':' - needed it to determine NS
		$GLOBALS['wgIllegalFileChars'] = str_replace( ":", "", $GLOBALS['wgIllegalFileChars'] );

		$this->requireExtension( 'NSFileRepo' );
	}

	public function execute() {
		$dbw = $this->getDB( DB_PRIMARY );
		print( "USING DB: " . $dbw->getDBName() . "\n" );
		$images = $dbw->select( 'oldimage',
			[ 'oi_name', 'oi_archive_name' ],
			[],
			__METHOD__
		);

		foreach ( $images as $image ) {
			$archiveTS = explode( '!', $image->oi_archive_name )[0];
			$archiveName = explode( '!', $image->oi_archive_name )[1];
			$archiveBits = explode( ':', $archiveName );
			if ( count( $archiveBits ) > 2 ) {
				$archiveNS = $archiveBits[0];
				$archiveFName = $archiveBits[count( $archiveBits ) - 1];
				unset( $archiveBits[count( $archiveBits ) - 1] );
				$archiveFill = implode( ':', $archiveBits );
				print( "Removing: " . $archiveFill . PHP_EOL );
				$dbw->update(
					'oldimage',
					[
						'oi_archive_name = ' . $dbw->strreplace( 'oi_archive_name',
							$dbw->addQuotes( $archiveFill ), $dbw->addQuotes( $archiveNS ) )
					],
					[ 'oi_name' => $image->oi_name ],
					__METHOD__
				);
			} else {
				continue;
			}
		}
	}
}

$maintClass = RemoveDuplicateNS::class;
require_once RUN_MAINTENANCE_IF_MAIN;
