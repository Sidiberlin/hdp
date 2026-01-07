<?php

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

use MediaWiki\Extension\NSFileRepo\File\NamespaceLocalFile;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Title\Title;

/**
 * This script checks if all files in DB have their
 * counterpart on FileSystem
 */
class CheckFiles extends Maintenance {

	public function __construct() {
		parent::__construct();
		// Remove the default illegal char ':' - needed it to determine NS
		$GLOBALS['wgIllegalFileChars'] = str_replace( ":", "", $GLOBALS['wgIllegalFileChars'] );

		$this->requireExtension( 'NSFileRepo' );
	}

	public function execute() {
		$dbr = $this->getDB( DB_REPLICA );
		print( "Using DB: " . $dbr->getDBName() ) . PHP_EOL;

		$aImgNames = [];
		$res = $dbr->select( 'image',
			[ 'img_name' ],
			[],
			__METHOD__
		);

		$repoGroup = $this->getServiceContainer()->getRepoGroup();

		foreach ( $res as $row ) {
			$sName = preg_replace( '/_/', ' ', $row->img_name );
			$oTitle = Title::makeTitle( NS_FILE, $sName );

			if ( !$oTitle->exists() ) {
					print ( "Title for " . $sName . " does not exist" . PHP_EOL );
					continue;
			}

			if ( $oTitle->getNamespace() !== NS_FILE ) {
					print ( "Title for " . $sName . " is not in NS_FILE" . PHP_EOL );
					continue;
			}

			$oFile = $repoGroup->findFile( $sName );
			if ( !$oFile || !$oFile->exists() ) {
					print( "File " . $sName . " does not exist!" . PHP_EOL );
			}
			$sFileLocalPath = $oFile->getLocalRefPath();
			if ( !$sFileLocalPath || !file_exists( $sFileLocalPath ) ) {
				print( "Image " . $sName . " not found!" . PHP_EOL );
			}
		}

		$res = $dbr->select( 'oldimage',
			[ 'oi_name', 'oi_archive_name' ],
			[],
			__METHOD__
		);

		foreach ( $res as $row ) {
			$oTitle = Title::makeTitle( NS_FILE, $row->oi_name );
			$repo = $repoGroup->getRepo( 'local' );
			$strippedName = NamespaceLocalFile::getFileNameStrippedStatic( $row->oi_archive_name );
			$file = OldLocalFile::newFromArchiveName( $oTitle, $repo, $strippedName );
			if ( !$file->getLocalRefPath() || !file_exists( $file->getLocalRefPath() ) ) {
				$file = OldLocalFile::newFromArchiveName( $oTitle, $repo, $row->oi_archive_name );
				print( "Archive file: " . $row->oi_archive_name . " not found" . PHP_EOL );
				if ( $file->getLocalRefPath() && file_exists( $file->getLocalRefPath() ) ) {
					print( "\t...but wrong version of this file exists: " . $file->getLocalRefPath() . PHP_EOL );
				}
			}
		}
	}
}

$maintClass = CheckFiles::class;
require_once RUN_MAINTENANCE_IF_MAIN;
