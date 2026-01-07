<?php

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

use MediaWiki\Extension\NSFileRepo\File\NamespaceLocalFile;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Title\Title;

class FixOldImage extends Maintenance {
	/** @var string */
	protected $mDBName;

	public function __construct() {
		parent::__construct();
		// Remove the default illegal char ':' - needed it to determine NS
		$GLOBALS['wgIllegalFileChars'] = str_replace( ":", "", $GLOBALS['wgIllegalFileChars'] );

		$this->requireExtension( 'NSFileRepo' );
	}

	/**
	 * @return void
	 */
	public function execute() {
		$dbw = $this->getDB( DB_PRIMARY );
		print( "USING DB: " . $dbw->getDBName() . "\n" );
		$images = $dbw->select( 'oldimage',
			[ 'oi_name', 'oi_archive_name' ],
			[],
			__METHOD__
		);

		$count = 0;
		$log = '';
		$repo = $this->getServiceContainer()->getRepoGroup()->getRepo( 'local' );

		foreach ( $images as $image ) {
			$nameBits = explode( ':', $image->oi_name );
			$nameNS = '';
			$nameName = $image->oi_name;
			if ( count( $nameBits ) == 2 ) {
				$nameNS = $nameBits[0];
				$nameName = $nameBits[1];
			}

			$archiveName = explode( '!', $image->oi_archive_name )[1];
			$archiveBits = explode( ':', $archiveName );
			$archiveNS = '';
			if ( count( $archiveBits ) == 2 ) {
				$archiveNS = $archiveBits[0];
				$archiveName = $archiveBits[1];
			}

			$title = Title::makeTitle( NS_FILE, $image->oi_name );
			if ( !$title || !$title->exists() ) {
				print( "Title wrong " . $image->oi_name . PHP_EOL );
				continue;
			}

			$strippedName = NamespaceLocalFile::getFileNameStrippedStatic( $image->oi_archive_name );

			$file = OldLocalFile::newFromArchiveName( $title, $repo, $strippedName );
			if ( !$file || !$file->exists() ) {
				$file = OldLocalFile::newFromArchiveName( $title, $repo, $image->oi_archive_name );
				if ( $file && $file->exists() && file_exists( $file->getLocalRefPath() ) ) {
					$path = $file->getLocalRefPath();
					print( "Found wrong file on FS: " . $path . "..." );
					$baseDir = dirname( $path );
					$renamed = rename( $path, $baseDir . '/' . $strippedName );
					if ( $renamed ) {
						print( "renamed" . PHP_EOL );
						$log .= "Renamed " . $path . " to " . $baseDir . '/' . $strippedName . "\n";
					} else {
						print( "failed to rename" . PHP_EOL );
						$log .= "Failed to rename " . $path . " to " . $baseDir . '/' . $strippedName . "\n";
					}

				}
			}

			if ( $nameNS === $archiveNS && $nameName == $archiveName ) {
				continue;
			}

			if ( $nameNS != $archiveNS && $nameName != $archiveName ) {
				continue;
			}

			print(
				"NAME: ns-" . $nameNS . ' name-' . $nameName .
				' ARCHIVE: ns-' . $archiveNS . ' name-' . $archiveName . "\n"
			);
			$log .= $nameNS . ";" . $nameName . ";" . $archiveNS . ";" . $archiveName . "\n";
			$dbw->update(
				'oldimage',
				[
					'oi_archive_name = ' . $dbw->strreplace( 'oi_archive_name',
						$dbw->addQuotes( $archiveName ), $dbw->addQuotes( $image->oi_name ) )
				],
				[ 'oi_archive_name' => $image->oi_archive_name ],
				__METHOD__
			);

			$count++;
		}
		file_put_contents( '/tmp/fixOldImagesLog.log', $log );
		print( "Total " . $count . " images altered\n" );
	}
}

$maintClass = FixOldImage::class;
require_once RUN_MAINTENANCE_IF_MAIN;
