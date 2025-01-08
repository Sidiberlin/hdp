<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

class AnalyzerResult {

	/**
	 * @var bool
	 */
	private $status = false;

	/**
	 * @var string
	 */
	private $filename = '';

	/**
	 * @var string
	 */
	private $namespace = '';

	/**
	 * @var string
	 */
	private $baseTitle = '';

	/**
	 * @var int
	 */
	private $split = 0;

	/**
	 * @var string[]
	 */
	private $categories = [];

	/**
	 * @var string[]
	 */
	private $titleMap = [];

	/**
	 * @var array
	 */
	private $fileExtensions = [];

	/**
	 * @var string
	 */
	private $nsFileRepoCompat = 'false';

	/**
	 * @param bool $status
	 * @param string $filename
	 * @param string $namespace
	 * @param string $baseTitle
	 * @param string $split
	 * @param array $categories
	 * @param array $titleMap
	 * @param array $fileExtensions
	 * @param bool $nsFilerepoCompat
	 */
	public function __construct( $status, $filename, $namespace, $baseTitle,
		$split, $categories, $titleMap, $fileExtensions, $nsFilerepoCompat
		) {
		$this->status = $status;
		$this->filename = $filename;
		$this->namespace = $namespace;
		$this->baseTitle = $baseTitle;
		$this->split = $split;
		$this->categories = $categories;
		$this->titleMap = $titleMap;
		$this->fileExtensions = $fileExtensions;
		$this->nsFileRepoCompat = $nsFilerepoCompat;
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
	public function getFilename(): string {
		return $this->filename;
	}

	/**
	 * @return int
	 */
	public function getNamespace(): string {
		return $this->namespace;
	}

	/**
	 * @return string
	 */
	public function getBaseTitle(): string {
		return $this->baseTitle;
	}

	/**
	 * @return int
	 */
	public function getSplit(): int {
		return (int)$this->split;
	}

	/**
	 * @return array
	 */
	public function getCategories(): array {
		return $this->categories;
	}

	/**
	 * @return bool
	 */
	public function getNsFileRepoCompat(): bool {
		if ( $this->nsFileRepoCompat === 'true' ) {
			return true;
		}
		return false;
	}

	/**
	 * @return array
	 */
	public function getTitleMap(): array {
		return $this->titleMap;
	}

	/**
	 * @return array
	 */
	public function getFileExtensions(): array {
		return $this->fileExtensions;
	}
}
