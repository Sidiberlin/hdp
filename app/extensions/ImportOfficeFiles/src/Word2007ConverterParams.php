<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

class Word2007ConverterParams {

	/**
	 * @var string
	 */
	private $namespace = '';

	/**
	 * @var string[]
	 */
	private $categories = [];

	/**
	 * @var bool
	 */
	private $verbose = false;

	/**
	 * @var string
	 */
	private $username = '';

	/**
	 * @var int
	 */
	private $imageWidthThreshold = 700;

	/**
	 * @param array $params
	 */
	public function __construct( $params ) {
		if ( isset( $params['namespace'] ) ) {
			$this->namespace = $params['namespace'];
		}
		if ( isset( $params['categories'] ) ) {
			$this->categories = $params['categories'];
		}
		if ( isset( $params['verbose'] ) ) {
			$this->verbose = $params['verbose'];
		}
		if ( isset( $params['username'] ) ) {
			$this->username = $params['username'];
		}
		if ( isset( $params['image-width-threshold'] ) ) {
			$this->imageWidthThreshold = $params['image-width-threshold'];
		}
	}

	/**
	 * @return int
	 */
	public function getNamespace(): string {
		return $this->namespace;
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
	public function getVerbose(): bool {
		return $this->verbose;
	}

	/**
	 * @return string
	 */
	public function getUsername(): string {
		return $this->username;
	}

	/**
	 * @return int
	 */
	public function getImageWidthThreshold(): int {
		return $this->imageWidthThreshold;
	}
}
