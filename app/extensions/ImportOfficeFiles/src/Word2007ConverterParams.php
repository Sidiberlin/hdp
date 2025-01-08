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
}
