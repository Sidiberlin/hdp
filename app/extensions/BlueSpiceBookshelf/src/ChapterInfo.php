<?php

namespace BlueSpice\Bookshelf;

class ChapterInfo {

	/** @var string */
	private $name = '';

	/** @var string */
	private $number = '';

	/** @var string */
	private $type = '';

	/**
	 * @param string $name
	 * @param string $number
	 * @param string $type
	 */
	public function __construct( string $name, string $number, string $type ) {
		$this->name = $name;
		$this->number = $number;
		$this->type = $type;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getNumber(): string {
		return $this->number;
	}

	/**
	 * @return string
	 */
	public function getType(): string {
		return $this->type;
	}
}
