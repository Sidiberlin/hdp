<?php

namespace BlueSpice\Bookshelf\HookHandler;

use BlueSpice\Bookshelf\BooksOverviewActions\Export;
use BlueSpice\Bookshelf\Hook\BSBookshelfBooksOverviewBeforeSetBookActions;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;

class AddBookActionExport implements BSBookshelfBooksOverviewBeforeSetBookActions {

	/**
	 * @inheritDoc
	 */
	public function onBSBookshelfBooksOverviewBeforeSetBookActions(
		array &$actions, Title $book, string $displayTitle
	): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'PDFCreator' ) ) {
			return;
		}
		$actions['export'] = new Export( $book, $displayTitle );
	}
}
