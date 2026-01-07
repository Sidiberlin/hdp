<?php

namespace BlueSpice\Bookshelf\Hook;

use MediaWiki\Title\Title;

interface BSBookshelfPageRemovedFromBookHook {

	/**
	 * @param Title $book
	 * @param Title $page
	 * @return void
	 */
	public function onBSBookshelfPageRemovedFromBook( Title $book, Title $page ): void;
}
