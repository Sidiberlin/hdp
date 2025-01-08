<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Component;

class Word2007DocumentData {

	/**
	 * Array, containing Word document relations data
	 *
	 * @var array
	 * @see \MediaWiki\Extension\ImportOfficeFiles\Reader\Word2007Reader::getRels()
	 */
	protected $rels;

	/**
	 * Array, containing Word document styles
	 *
	 * @var array
	 * @see \MediaWiki\Extension\ImportOfficeFiles\Reader\Word2007Reader::getStyles()
	 */
	protected $styles;

	/**
	 * Data object, containing Word document numbering data
	 *
	 * @var Word2007Numbering
	 * @see \MediaWiki\Extension\ImportOfficeFiles\Reader\Component\Word2007Numbering
	 */
	protected $numbering;

	/**
	 * @param array $styles
	 * @param array $rels
	 * @param Word2007Numbering|null $numbering
	 */
	public function __construct( array $styles, array $rels, Word2007Numbering $numbering ) {
		$this->styles = $styles;
		$this->rels = $rels;
		$this->numbering = $numbering;
	}

	/**
	 * @return array
	 * @see Word2007DocumentData::$rels
	 */
	public function getRels(): array {
		return $this->rels;
	}

	/**
	 * @return array
	 * @see Word2007DocumentData::$styles
	 */
	public function getStyles(): array {
		return $this->styles;
	}

	/**
	 * @return Word2007Numbering
	 * @see Word2007DocumentData::$numbering
	 */
	public function getNumbering(): Word2007Numbering {
		return $this->numbering;
	}
}
