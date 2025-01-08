<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

class Segment {

	/**
	 * @var int
	 */
	private $level;

	/**
	 * @var string
	 */
	private $label;

	/**
	 * @var string
	 */
	private $filePath;

	/**
	 * @param int $level
	 * @param string $label
	 * @param string $filePath
	 */
	public function __construct( $level, $label, $filePath ) {
		$this->level = $level;
		$this->label = $label;
		$this->filePath = $filePath;
	}

	/**
	 * @return int
	 */
	public function getLevel(): int {
		return (int)$this->level;
	}

	/**
	 * @return string
	 */
	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * @return string
	 */
	public function getFilePath(): string {
		return $this->filePath;
	}
}
