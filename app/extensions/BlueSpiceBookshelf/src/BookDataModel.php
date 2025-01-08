<?php

namespace BlueSpice\Bookshelf;

class BookDataModel {

	/**
	 * @var int|null
	 */
	private $namespace = null;

	/**
	 * @var string|null
	 */
	private $title = null;

	/**
	 * @var string
	 */
	private $name = '';

	/**
	 * @var string
	 */
	private $type = '';

	/**
	 * @param int|null $namespace
	 * @param string|null $title
	 * @param string $name
	 * @param string $type
	 */
	public function __construct( $namespace, $title, $name, $type ) {
		$this->namespace = $namespace;
		$this->title = $title;
		$this->name = $name;
		$this->type = $type;
	}

	/**
	 * @return int|null
	 */
	public function getNamespace() {
		return $this->namespace;
	}

	/**
	 * @return string|null
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}
}
