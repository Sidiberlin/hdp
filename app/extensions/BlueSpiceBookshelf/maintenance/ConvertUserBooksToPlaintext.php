<?php

use MediaWiki\Maintenance\LoggedUpdateMaintenance;

require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/maintenance/Maintenance.php';

class ConvertUserBooksToPlaintext extends LoggedUpdateMaintenance {

	/**
	 * @return bool
	 */
	protected function doDBUpdates() {
		// User books no longer supported - convert to plain wikitext linklist, for B/C
		$this->output( "...Update '" . $this->getUpdateKey() . "': " );
		return $this->getDB( DB_PRIMARY )->update(
			'page',
			[ 'page_content_model' => 'wikitext' ],
			[ 'page_namespace' => [ NS_USER ], 'page_content_model' => 'book' ],
			__METHOD__
		);
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'bs_bookshelf_convert_user_books_to_plaintext';
	}
}

$maintClass = ConvertUserBooksToPlaintext::class;
require_once RUN_MAINTENANCE_IF_MAIN;
