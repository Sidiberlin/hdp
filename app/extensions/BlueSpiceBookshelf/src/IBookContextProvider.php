<?php

namespace BlueSpice\Bookshelf;

use MediaWiki\Title\Title;

interface IBookContextProvider {

	/**
	 * @return bool
	 */
	public function isResponsible(): bool;

	/**
	 * Returns the book title that should be used
	 * for the current page
	 *
	 * @return Title|null
	 */
	public function getActiveBook(): ?Title;

	/**
	 * @return int
	 */
	public function getPriority(): int;
}
