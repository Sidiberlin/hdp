<?php

namespace BlueSpice\Bookshelf;

class BookInfo {

	/** @var string */
	private $id = '';

	/** @var string */
	private $namespace = '';

	/** @var string */
	private $title = '';

	/** @var string */
	private $name = '';

	/** @var string */
	private $type = '';

	/**
	 * @param string $id
	 * @param string $namespace
	 * @param string $title
	 * @param string $name
	 * @param string $type
	 */
	public function __construct(
		string $id, string $namespace, string $title, string $name, string $type
	) {
		$this->id = $id;
		$this->namespace = $namespace;
		$this->title = $title;
		$this->name = $name;
		$this->type = $type;
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getTitle(): string {
		return $this->title;
	}

	/**
	 * @return string
	 */
	public function getNamespace(): string {
		return $this->namespace;
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
	public function getType(): string {
		return $this->type;
	}
}
