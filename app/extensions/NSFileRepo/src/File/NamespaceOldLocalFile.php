<?php

namespace MediaWiki\Extension\NSFileRepo\File;

class NamespaceOldLocalFile extends \OldLocalFile {

	/**
	 * @var NamespaceLocalFile
	 */
	protected $internalFile;

	/**
	 * @inheritDoc
	 */
	public function __construct( $title, $repo, $time, $archiveName ) {
		parent::__construct( $title, $repo, $time, $archiveName );
		$this->internalFile = new NamespaceLocalFile( $title, $repo );
	}

	/**
	 * @return string|null
	 */
	public function getArchiveName(): ?string {
		return $this->internalFile->getFileNameStripped( parent::getArchiveName() );
	}

	/**
	 * @inheritDoc
	 */
	public function publish( $src, $flags = 0, array $options = [] ) {
		return $this->internalFile->publish( $src, $flags, $options );
	}

	/**
	 * @inheritDoc
	 */
	public function getThumbUrl( $suffix = false ) {
		return $this->internalFile->getArchiveThumbUrl(
			$this->getFileNameStripped( $this->getArchiveName() ), $suffix
		);
	}

	/**
	 * @inheritDoc
	 */
	public function thumbName( $params, $flags = 0 ) {
		return $this->internalFile->thumbName( $params, $flags );
	}

	/**
	 * @inheritDoc
	 */
	public function getThumbPath( $suffix = false ) {
		return $this->internalFile->getArchiveThumbPath(
			$this->getFileNameStripped( $this->getArchiveName() ), $suffix
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getArchiveRel( $suffix = false ) {
		return $this->internalFile->getArchiveRel( $suffix );
	}

	/**
	 * @inheritDoc
	 */
	public function getArchiveUrl( $suffix = false ) {
		return $this->internalFile->getArchiveUrl( $suffix );
	}

	/**
	 * @inheritDoc
	 */
	public function getArchiveVirtualUrl( $suffix = false ) {
		return $this->internalFile->getArchiveVirtualUrl( $suffix );
	}

	/**
	 * @inheritDoc
	 */
	public function getThumbVirtualUrl( $suffix = false ) {
		return $this->internalFile->getThumbVirtualUrl( $suffix );
	}

	/**
	 * @inheritDoc
	 */
	public function getVirtualUrl( $suffix = false ) {
		return $this->internalFile->getVirtualUrl( $suffix );
	}

	/**
	 * @param string $suffix
	 * @return string
	 */
	public function getFileNameStripped( string $suffix ) {
		return $this->internalFile->getFileNameStripped( $suffix );
	}

	/**
	 * @inheritDoc
	 */
	public function purgeThumbnails( $options = [] ) {
		$this->internalFile->purgeThumbnails( $options );
	}

	/**
	 * Replaces hard coded OldLocalFile::newFromRow to use $this->repo->oldFileFromRowFactory configuration
	 * This may not be necessary in the future if LocalFile is patched to allow configuration
	 *
	 * @param int|null $limit
	 * @param int|null $start
	 * @param int|null $end
	 * @param bool $inc
	 * @return array
	 */
	public function getHistory( $limit = null, $start = null, $end = null, $inc = true ) {
		return $this->internalFile->getHistory( $limit, $start, $end, $inc );
	}

}
