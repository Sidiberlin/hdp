<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

class ConverterResult {

	/**
	 * @var string
	 */
	private $filepath;

	/**
	 * @var string
	 */
	private $imagesDir;

	/**
	 * @var string
	 */
	private $collection;

	/**
	 * @param bool $status
	 * @param string $importFilepath
	 * @param string $imagesDir
	 * @param string $collection
	 */
	public function __construct( $status, $importFilepath, $imagesDir, $collection = '' ) {
		$this->status = $status;
		$this->filepath = $importFilepath;
		$this->imagesDir = $imagesDir;
		$this->collection = $collection;
	}

	/**
	 * false = step not done yet
	 * true = step finished
	 *
	 * @return bool
	 */
	public function getStatus(): bool {
		return $this->status;
	}

	/**
	 * @return string
	 */
	public function getFilePath(): string {
		return $this->filepath;
	}

	/**
	 * @return string
	 */
	public function getImagesDir(): string {
		return $this->imagesDir;
	}

	/**
	 * @return string
	 */
	public function getCollection(): string {
		return $this->collecton;
	}
}
