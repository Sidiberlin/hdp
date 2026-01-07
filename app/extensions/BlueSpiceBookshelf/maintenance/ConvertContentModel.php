<?php

use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IDatabase;

require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/maintenance/Maintenance.php';

class ConvertContentModel extends LoggedUpdateMaintenance {

	/**
	 * @var int
	 */
	private $bookContentModelId;

	/**
	 * @param IDatabase $db
	 * @return array
	 */
	private function getPages( $db ) {
		$res = $db->select(
			'page',
			[ 'page_id', 'page_namespace', 'page_title', 'page_content_model' ],
			[ 'page_namespace' => [ NS_BOOK ] ],
			__METHOD__
		);

		$pages = [];
		foreach ( $res as $row ) {
			$title = Title::newFromRow( $row );
			if ( !$title ) {
				continue;
			}
			if ( $row->page_content_model === 'book' ) {
				$this->assertRevisionContentModel( $title );
				// Nothing to do
				continue;
			}

			$pages[(int)$row->page_id] = $title;
		}

		return $pages;
	}

	/**
	 * @param Title[] $pages
	 * @param IDatabase $db
	 * @return bool
	 */
	private function convert( $pages, IDatabase $db ) {
		if ( empty( $pages ) ) {
			return true;
		}

		$res = $db->update(
			'page',
			[
				'page_content_model' => 'book'
			],
			[
				'page_id' => array_keys( $pages )
			],
			__METHOD__
		);
		if ( $res ) {
			foreach ( $pages as $title ) {
				$this->convertRevisionContentModel( $title );
				$title->invalidateCache();
			}
		}
		return $res;
	}

	/**
	 * @return bool
	 */
	protected function doDBUpdates() {
		$this->output( "...Update '" . $this->getUpdateKey() . "': " );
		$dbLoadBalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$this->bookContentModelId = $this->getBookContentModelId();
		$pages = $this->getPages(
			$dbLoadBalancer->getConnection( DB_REPLICA )
		);
		$res = $this->convert(
			$pages,
			$dbLoadBalancer->getConnection( DB_PRIMARY )
		);
		$this->output( "OK\n" );
		return $res;
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'bs_bookmaker_convert_content_model3';
	}

	/**
	 * @param Title $title
	 * @return void
	 */
	private function assertRevisionContentModel( Title $title ) {
		$this->convertRevisionContentModel( $title );
	}

	/**
	 * @param Title $title
	 */
	private function convertRevisionContentModel( Title $title ) {
		$toConvert = $this->getDB( DB_REPLICA )->select(
			[ 'r' => 'revision', 's' => 'slots', 'c' => 'content' ],
			[ 'rev_id', 'content_id' ],
			[
				'rev_page' => $title->getArticleID(),
				'content_model != ' . $this->bookContentModelId
			],
			__METHOD__,
			[],
			[
				's' => [ 'INNER JOIN', 'r.rev_id = s.slot_revision_id' ],
				'c' => [ 'INNER JOIN', 's.slot_content_id = c.content_id' ]
			]
		);
		if ( !$toConvert->numRows() ) {
			return;
		}
		$ids = [];
		foreach ( $toConvert as $row ) {
			$ids[] = $row->content_id;
		}
		$db = $this->getDB( DB_PRIMARY );
		$db->update(
			'content',
			[ 'content_model' => $this->bookContentModelId ],
			[ 'content_id IN (' . $db->makeList( $ids ) . ')' ],
			__METHOD__
		);
	}

	/**
	 * @param bool $hadCreated
	 * @return int
	 * @throws MWException
	 */
	private function getBookContentModelId( bool $hadCreated = false ) {
		$res = $this->getDB( DB_REPLICA )->selectRow(
			'content_models',
			[ 'model_id' ],
			[ 'model_name' => 'book' ],
			__METHOD__
		);
		if ( !$res ) {
			if ( $hadCreated ) {
				throw new MWException( "Content model 'book' not found and couldnt be created" );
			}
			$this->getDB( DB_PRIMARY )->insert(
				'content_models',
				[ 'model_name' => 'book' ],
				__METHOD__
			);
			return $this->getBookContentModelId( true );
		}
		return (int)$res->model_id;
	}

}

$maintClass = ConvertContentModel::class;
require_once RUN_MAINTENANCE_IF_MAIN;
