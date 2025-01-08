<?php

namespace BlueSpice\Bookshelf;

class ChapterDataModel {

	public const WIKILINK_WITH_ALIAS = 'wikilink-with-alias';
	public const PLAIN_TEXT = 'plain-text';

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
	private $number = '';

	/**
	 * @var string
	 */
	private $type = '';

	/**
	 * @param int|null $namespace
	 * @param string|null $title
	 * @param string $name
	 * @param string $number
	 * @param string $type
	 */
	public function __construct( $namespace, $title, $name, $number, $type ) {
		$this->namespace = $namespace;
		$this->title = $title;
		$this->name = $name;
		$this->number = $number;
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
	public function getNumber() {
		return $this->number;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}
}
