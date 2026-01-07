<?php

namespace BlueSpice\Bookshelf\Hook;

use MediaWiki\Title\Title;

interface BSBookshelfBooksOverviewBeforeSetBookActions {

	/**
	 *
	 * @param array &$actions
	 * @param Title $book
	 * @param string $displayTitle
	 * @return void
	 */
	public function onBSBookshelfBooksOverviewBeforeSetBookActions(
		array &$actions, Title $book, string $displayTitle
	): void;
}
