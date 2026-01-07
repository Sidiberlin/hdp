<?php

namespace MediaWiki\Extension\NSFileRepo;

use File;
use LocalFile;
use MediaWiki\Extension\NSFileRepo\File\NamespaceLocalFile;
use MediaWiki\Title\Title;

class NamespaceFileMoveBatch extends \LocalFileMoveBatch {

	/**
	 * @param LocalFile $file
	 * @param Title $target
	 */
	public function __construct( LocalFile $file, Title $target ) {
		parent::__construct( $file, $target );
		$this->oldRel = $this->oldHash . NamespaceLocalFile::getFileNameStrippedStatic( $this->oldName );
		$this->newRel = $this->newHash . NamespaceLocalFile::getFileNameStrippedStatic( $this->newName );
	}

	/**
	 * @return array
	 */
	public function addOlds() {
		$newName = NamespaceLocalFile::getFileNameStrippedStatic( $this->newName );
		$archiveBase = 'archive';
		$this->olds = [];
		$this->oldCount = 0;
		$archiveNames = [];

		$result = $this->db->newSelectQueryBuilder()
			->select( [ 'oi_archive_name', 'oi_deleted' ] )
			->forUpdate()
			->from( 'oldimage' )
			->where( [ 'oi_name' => $this->oldName ] )
			->caller( __METHOD__ )->fetchResultSet();

		foreach ( $result as $row ) {
			$archiveNames[] = $row->oi_archive_name;
			$oldName = $row->oi_archive_name;
			$bits = explode( '!', $oldName, 2 );

			if ( count( $bits ) != 2 ) {
				continue;
			}

			[ $timestamp, $filename ] = $bits;

			if ( $this->oldName != $filename ) {
				continue;
			}
			$strippedOldName = NamespaceLocalFile::getFileNameStrippedStatic( $oldName );

			$this->oldCount++;

			// Do we want to add those to oldCount?
			if ( $row->oi_deleted & File::DELETED_FILE ) {
				continue;
			}

			$this->olds[] = [
				"$archiveBase/$this->oldHash$strippedOldName",
				"$archiveBase/$this->newHash$timestamp!$newName"
			];
		}

		return $archiveNames;
	}
}
