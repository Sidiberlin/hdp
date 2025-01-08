<?php

namespace BlueSpice\Bookshelf\MenuEditor\Node;

use MediaWiki\Extension\MenuEditor\Node\MenuNode;

class ChapterPlainText extends MenuNode {
	/** @var string */
	private $text;

	/**
	 * @param int $level
	 * @param string $text
	 * @param string $originalWikitext
	 */
	public function __construct( int $level, $text, $originalWikitext = '' ) {
		parent::__construct( $level, $originalWikitext );
		$this->text = $text;
	}

	/**
	 * @return string
	 */
	public function getType(): string {
		return 'bs-bookshelf-chapter-plain-text';
	}

	/**
	 * @param string $text
	 */
	public function setNodeText( string $text ) {
		$this->text = $text;
	}

	/**
	 * @return string
	 */
	public function getNodeText(): string {
		return $this->text;
	}

	/**
	 * @return string
	 */
	public function getCurrentData(): string {
		return "{$this->getLevelString()} {$this->getNodeText()}";
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'type' => $this->getType(),
			'level' => $this->getLevel(),
			'text' => $this->getNodeText(),
			'wikitext' => $this->getCurrentData()
		];
	}
}
