<?php

namespace MediaWiki\Extension\NSFileRepo\File;

use LocalFile;
use MediaWiki\Deferred\AutoCommitUpdate;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\NSFileRepo\NamespaceFileMoveBatch;
use MediaWiki\MediaWikiServices;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use OldLocalFile;

class NamespaceLocalFile extends LocalFile {

	/**
	 * @inheritDoc
	 */
	public function getRel() {
		return $this->getHashPath() . $this->getFileNameStripped( $this->getName() );
	}

	/**
	 * @inheritDoc
	 */
	public function getArchiveRel( $suffix = false ) {
		$path = 'archive/' . $this->getHashPath();
		if ( $suffix === false ) {
			$path = rtrim( $path, '/' );
		} else {
			$path .= $this->getFileNameStripped( $suffix );
		}

		return $path;
	}

	/**
	 * @inheritDoc
	 */
	public function getUrlRel() {
		return $this->getHashPath() . rawurlencode( $this->getFileNameStripped( $this->getName() ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getThumbUrl( $suffix = false ) {
		$path = $this->repo->getZoneUrl( 'thumb' ) . '/' . $this->getUrlRel();
		if ( $suffix !== false ) {
			$path .= '/' . rawurlencode( $this->getFileNameStripped( $suffix ) );
		}
		return $path;
	}

	/**
	 * @inheritDoc
	 */
	public function thumbName( $params, $flags = 0 ) {
		$name = ( $this->repo && !( $flags & self::THUMB_FULL_NAME ) )
			? $this->repo->nameForThumb( $this->getFileNameStripped( $this->getName() ) )
			: $this->getFileNameStripped( $this->getName() );

		return $this->generateThumbName( $name, $params );
	}

	/**
	 * @inheritDoc
	 */
	public function getThumbRel( $suffix = false ) {
		$path = $this->getRel();
		if ( $suffix !== false ) {
			$path .= '/' . $this->getFileNameStripped( $suffix );
		}

		return $path;
	}

	/**
	 * @inheritDoc
	 */
	public function generateThumbName( $name, $params ) {
		return $this->getFileNameStripped( parent::generateThumbName( $name, $params ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getArchivePath( $suffix = false ) {
		if ( !$suffix ) {
			return parent::getArchivePath( $suffix );
		}
		$suffix = $this->getFileNameStripped( $suffix );
		return parent::getArchivePath( $suffix );
	}

	/**
	 * @inheritDoc
	 */
	public function getArchiveThumbPath( $archiveName, $suffix = false ) {
		if ( !$suffix ) {
			return parent::getArchiveThumbPath( $archiveName, $suffix );
		}
		$suffix = $this->getFileNameStripped( $suffix );
		return parent::getArchiveThumbPath( $archiveName, $suffix );
	}

	/**
	 * @inheritDoc
	 */
	public function getArchiveThumbUrl( $archiveName, $suffix = false ) {
		if ( !$suffix ) {
			return parent::getArchiveThumbPath( $archiveName, $suffix );
		}
		$suffix = $this->getFileNameStripped( $suffix );
		return parent::getArchiveThumbUrl( $archiveName, $suffix );
	}

	/**
	 * @inheritDoc
	 */
	public function getArchiveVirtualUrl( $suffix = false ) {
		if ( !$suffix ) {
			return parent::getArchiveVirtualUrl( $suffix );
		}
		$suffix = $this->getFileNameStripped( $suffix );
		return parent::getArchiveVirtualUrl( $suffix );
	}

	/**
	 * @inheritDoc
	 */
	public function getThumbVirtualUrl( $suffix = false ) {
		if ( !$suffix ) {
			return parent::getThumbVirtualUrl( $suffix );
		}
		$suffix = $this->getFileNameStripped( $suffix );
		return parent::getThumbVirtualUrl( $suffix );
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public function getFileNameStripped( string $name ): string {
		return static::getFileNameStrippedStatic( $name );
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public static function getFileNameStrippedStatic( string $name ): string {
		$colonIndex = strpos( $name, ":" );
		if ( $colonIndex ) {
			$final = '';
			$tsIndex = strpos( $name, "!" );

			if ( $tsIndex ) {
				$final = substr( $name, 0, $tsIndex + 1 );
			}
			$final .= substr( $name, $colonIndex + 1, strlen( $name ) - 1 );

			return $final;
		}
		return $name;
	}

	/**
	 * Delete a list of thumbnails visible at urls
	 * @stable to override
	 * @param string $dir Base dir of the files.
	 * @param array $files Array of strings: relative filenames (to $dir)
	 */
	protected function purgeThumbList( $dir, $files ) {
		$fileListDebug = strtr(
			var_export( $files, true ),
			[ "\n" => '' ]
		);
		wfDebug( __METHOD__ . ": $fileListDebug" );

		if ( $this->repo->supportsSha1URLs() ) {
			$reference = $this->getSha1();
		} else {
			$reference = $this->getFileNameStripped( $this->getName() );
		}

		$purgeList = [];
		foreach ( $files as $file ) {
			# Check that the reference (filename or sha1) is part of the thumb name
			# This is a basic check to avoid erasing unrelated directories
			if ( str_contains( $file, $reference )
				|| str_contains( $file, "-thumbnail" )
			) {
				$purgeList[] = "{$dir}/{$file}";
			}
		}

		# Delete the thumbnails
		$this->repo->quickPurgeBatch( $purgeList );
		# Clear out the thumbnail directory if empty
		$this->repo->quickCleanDir( $dir );
	}

	/**
	 * @param Title $target
	 * @return Status
	 */
	public function move( $target ) {
		$localRepo = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo();
		if ( $this->getRepo()->getReadOnlyReason() !== false ) {
			return $this->readOnlyFatalStatus();
		}

		wfDebugLog( 'imagemove', "Got request to move {$this->name} to " . $target->getText() );
		$batch = new NamespaceFileMoveBatch( $this, $target );

		$status = $batch->addCurrent();
		if ( !$status->isOK() ) {
			return $status;
		}
		$archiveNames = $batch->addOlds();
		$status = $batch->execute();

		wfDebugLog( 'imagemove', "Finished moving {$this->name}" );

		// Purge the source and target files outside the transaction...
		$oldTitleFile = $localRepo->newFile( $this->title );
		$newTitleFile = $localRepo->newFile( $target );
		DeferredUpdates::addUpdate(
			new AutoCommitUpdate(
				$this->getRepo()->getPrimaryDB(),
				__METHOD__,
				static function () use ( $oldTitleFile, $newTitleFile, $archiveNames ) {
					$oldTitleFile->purgeEverything();
					foreach ( $archiveNames as $archiveName ) {
						/** @var OldLocalFile $oldTitleFile */
						'@phan-var OldLocalFile $oldTitleFile';
						$oldTitleFile->purgeOldThumbnails( $archiveName );
					}
					$newTitleFile->purgeEverything();
				}
			),
			DeferredUpdates::PRESEND
		);

		if ( $status->isOK() ) {
			// Now switch the object
			$this->title = $target;
			// Force regeneration of the name and hashpath
			$this->name = null;
			$this->hashPath = null;
		}

		return $status;
	}
}
