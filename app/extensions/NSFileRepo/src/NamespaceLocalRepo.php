<?php

namespace MediaWiki\Extension\NSFileRepo;

use LocalRepo;
use MediaWiki\Extension\NSFileRepo\File\NamespaceLocalFile;
use MediaWiki\Extension\NSFileRepo\File\NamespaceOldLocalFile;
use MediaWiki\MediaWikiServices;
use MediaWiki\Status\Status;

/**
 * Class definitions for NSFileRepo
 */
class NamespaceLocalRepo extends LocalRepo {
	/** @var string[] */
	protected $fileFactory = [ NamespaceLocalFile::class, 'newFromTitle' ];
	/** @var string[] */
	protected $fileFactoryKey = [ NamespaceLocalFile::class, 'newFromKey' ];
	/** @var string[] */
	protected $oldFileFactory = [ NamespaceOldLocalFile::class, 'newFromTitle' ];
	/** @var string[] */
	protected $fileFromRowFactory = [ NamespaceLocalFile::class, 'newFromRow' ];
	/** @var string[] */
	protected $oldFileFromRowFactory = [ NamespaceOldLocalFile::class, 'newFromRow' ];
	/** @var string[] */
	protected $oldFileFactoryKey = [ NamespaceOldLocalFile::class, 'newFromKey' ];

	/**
	 * Get a relative path including trailing slash, e.g. f/fa/
	 * If the repo is not hashed, returns an empty string
	 *
	 * @param string $name Name of file
	 * @return string
	 */
	public function getHashPath( $name ) {
		return static::getHashPathForLevel( $name, $this->hashLevels );
	}

	/**
	 * Get a relative path including trailing slash, e.g. f/fa/
	 * If the repo is not hashed, returns an empty string
	 *
	 * @param string $suffix Basename of file from FileRepo::storeTemp()
	 * @return string
	 */
	public function getTempHashPath( $suffix ) {
		// format is <timestamp>!<name> or just <name>
		$parts = explode( '!', $suffix, 2 );
		// hash path is not based on timestamp
		$name = $parts[1] ?? $suffix;
		return static::getHashPathForLevel( $name, $this->hashLevels );
	}

	/**
	 * @inheritDoc
	 */
	public static function getHashPathForLevel( $name, $levels ) {
		$bits = explode( ':', $name );
		$filename = $bits[count( $bits ) - 1];
		$path = parent::getHashPathForLevel( $filename, $levels );
		return count( $bits ) > 1 ?
			MediaWikiServices::getInstance()->getContentLanguage()->getNsIndex( $bits[0] ) . '/' . $path : $path;
	}

	/**
	 * Pick a random name in the temp zone and store a file to it.
	 * @param string $originalName The base name of the file as specified
	 *     by the user. The file extension will be maintained.
	 * @param string $srcPath The current location of the file.
	 * @return Status object with the URL in the value.
	 */
	public function storeTemp( $originalName, $srcPath ) {
		$date = gmdate( "YmdHis" );
		$hashPath = $this->getHashPath( $originalName );
		$filename = $this->getFileNameStripped( $originalName );
		$dstRel = "$hashPath$date!$filename";
		$dstUrlRel = $hashPath . $date . '!' . rawurlencode( $filename );
		$result = $this->store( $srcPath, 'temp', $dstRel );
		$result->value = $this->getVirtualUrl( 'temp' ) . '/' . $dstUrlRel;
		return $result;
	}

	/**
	 * @param string $suffix
	 * @return string
	 */
	private function getFileNameStripped( $suffix ) {
		return NamespaceLocalFile::getFileNameStrippedStatic( $suffix );
	}
}
