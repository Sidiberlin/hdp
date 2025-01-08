<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

class Word2007AnalyzerParams {

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
	 * @var string
	 */
	private $nsFileRepoCompat = 'false';

	/**
	 * @var string[]
	 */
	private $titleMap = [];

	/**
	 * @var bool
	 */
	private $verbose = false;

	/**
	 * @var bool
	 */
	private $uncollide = false;

	/**
	 * @param array $params
	 */
	public function __construct( $params ) {
		if ( isset( $params['filename'] ) ) {
			$this->filename = $params['filename'];
		}
		if ( isset( $params['namespace'] ) ) {
			$this->namespace = $params['namespace'];
		}
		if ( isset( $params['base-title'] ) ) {
			$this->baseTitle = $params['base-title'];
		}
		if ( isset( $params['split'] ) ) {
			$this->split = $params['split'];
		}
		if ( isset( $params['categories'] ) ) {
			$this->categories = $params['categories'];
		}
		if ( isset( $params['ns-filerepo-compat'] ) ) {
			$this->nsFileRepoCompat = $params['ns-filerepo-compat'];
		}
		if ( isset( $params['title-map'] ) ) {
			$this->titleMap = $params['title-map'];
		}
		if ( isset( $params['verbose'] ) ) {
			$this->verbose = $params['verbose'];
		}
		if ( isset( $params['uncollide'] ) ) {
			$this->uncollide = $params['uncollide'];
		}
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
		if ( $this->nsFileRepoCompat === true ) {
			return true;
		} elseif ( $this->nsFileRepoCompat === 'true' ) {
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
	 * @return bool
	 */
	public function getVerbose(): bool {
		return $this->verbose;
	}

	/**
	 * @return bool
	 */
	public function getUncollideTitles(): bool {
		if ( $this->uncollide === true ) {
			return true;
		} elseif ( $this->uncollide === 'true' ) {
			return true;
		}
		return false;
	}
}
