<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LoadBalancer;

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

class FixBookChapterTitles extends LoggedUpdateMaintenance {

	/** @var IDatabase */
	private $db = null;

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var Title[] */
	private $chapters = [];

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'bookshelf-update-book-chapters';
	}

	/**
	 *
	 */
	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'BlueSpiceBookshelf' );
	}

	/**
	 * @return bool
	 */
	protected function doDBUpdates() {
		$this->output( "BlueSpiceBookshelf - fix bs_book_chapters\n" );

		$services = MediaWikiServices::getInstance();

		/** @var LoadBalancer */
		$loadBalancer = $services->getDBLoadBalancer();

		$this->db = $loadBalancer->getConnection( DB_PRIMARY );
		$this->titleFactory = $services->getTitleFactory();

		$this->fetchChapters();
		$this->updateBookChaptersTable();

		return true;
	}

	private function fetchChapters() {
		$res = $this->db->select(
			'bs_book_chapters',
			'*'
		);

		if ( $res->numRows() < 1 ) {
			return;
		}

		foreach ( $res as $row ) {
			if ( (int)$row->chapter_namespace === NS_USER ) {
				continue;
			}
			$title = $this->titleFactory->makeTitle( $row->chapter_namespace, $row->chapter_title );
			$key = $title->getPrefixedDBkey();
			$this->chapters[$row->chapter_id] = $title;
		}
	}

	private function updateBookChaptersTable() {
		foreach ( $this->chapters as $id => $chapter ) {
			$this->output( "Update " . $chapter->getText() . " into 'bs_book_chapters' ..." );
			$this->db->update(
				'bs_book_chapters',
				[
					'chapter_title' => $chapter->getDBKey()
				], [
					'chapter_id' => $id
				]
			);
			$this->output( "\033[32m  done\n\033[39m" );
		}
	}
}

$maintClass = FixBookChapterTitles::class;
require_once RUN_MAINTENANCE_IF_MAIN;
