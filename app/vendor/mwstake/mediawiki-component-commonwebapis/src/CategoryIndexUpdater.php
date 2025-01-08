<?php

namespace MWStake\MediaWiki\Component\CommonWebAPIs;

use Category;
use ManualLogEntry;
use MediaWiki\Hook\AfterImportPageHook;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\Page\Hook\ArticleUndeleteHook;
use MediaWiki\Page\Hook\CategoryAfterPageAddedHook;
use MediaWiki\Page\Hook\CategoryAfterPageRemovedHook;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use Wikimedia\Rdbms\ILoadBalancer;

class CategoryIndexUpdater implements
	CategoryAfterPageAddedHook,
	CategoryAfterPageRemovedHook,
	PageSaveCompleteHook,
	PageMoveCompleteHook,
	PageDeleteCompleteHook,
	ArticleUndeleteHook,
	AfterImportPageHook
{

	/**
	 * @var ILoadBalancer
	 */
	private $lb;

	/**
	 * @param ILoadBalancer $lb
	 */
	public function __construct( ILoadBalancer $lb ) {
		$this->lb = $lb;
	}

	/**
	 * @inheritDoc
	 */
	public function onCategoryAfterPageAdded( $category, $wikiPage ) {
		$this->updateForCategory( $category );
	}

	/**
	 * @inheritDoc
	 */
	public function onCategoryAfterPageRemoved( $category, $wikiPage, $id ) {
		$this->updateForCategory( $category );
	}

	/**
	 * @inheritDoc
	 */
	public function onAfterImportPage( $title, $foreignTitle, $revCount, $sRevCount, $pageInfo ) {
		if ( $title->getNamespace() === NS_CATEGORY ) {
			$this->updateForPage( $title );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onArticleUndelete( $title, $create, $comment, $oldPageId, $restoredPages ) {
		if ( $title->getNamespace() === NS_CATEGORY ) {
			$this->updateForPage( $title );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onPageDeleteComplete(
		ProperPageIdentity $page, Authority $deleter, string $reason, int $pageID,
		RevisionRecord $deletedRev, ManualLogEntry $logEntry, int $archivedRevisionCount
	) {
		if ( $page->getNamespace() === NS_CATEGORY ) {
			$this->delete( $page->getDBkey() );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		if ( $old->getNamespace() === NS_CATEGORY ) {
			$this->delete( $old->getDBkey() );
		}
		if ( $new->getNamespace() === NS_CATEGORY ) {
			$this->updateForPage( $new );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		if ( $wikiPage->getTitle()->getNamespace() === NS_CATEGORY ) {
			$this->updateForPage( $wikiPage->getTitle() );
		}
	}

	/**
	 * @param Category $category
	 * @return void
	 */
	private function updateForCategory( Category $category ) {
		$this->updateForPage( $category->getPage() );
	}

	/**
	 * @param PageIdentity $page
	 * @return void
	 */
	private function updateForPage( PageIdentity $page ) {
		$categoryKey = $page->getDBkey();
		$this->delete( $categoryKey );
		$info = $this->getCategoryInfo( $page );
		$this->insert( $info );
	}

	/**
	 * @param string $categoryKey
	 * @return void
	 */
	private function delete( string $categoryKey ) {
		$dbw = $this->lb->getConnection( DB_PRIMARY );
		if ( !$dbw->tableExists( 'mws_category_index' ) ) {
			return;
		}
		$dbw->delete( 'mws_category_index', [
			'mci_title' => mb_strtolower( str_replace( '_', ' ', $categoryKey ) )
		] );
	}

	/**
	 * @param PageIdentity $page
	 * @return array
	 */
	private function getCategoryInfo( PageIdentity $page ): array {
		$dbr = $this->lb->getConnection( DB_REPLICA );
		$catRow = $dbr->selectRow(
			'category',
			[ 'cat_id', 'cat_pages' ],
			[ 'cat_title' => $page->getDBkey() ],
			__METHOD__
		);
		$pageCount = $catRow ? (int)$catRow->cat_pages : 0;
		$catId = $catRow ? (int)$catRow->cat_id : 0;
		return [
			'mci_cat_id' => $catId,
			'mci_title' => mb_strtolower( str_replace( '_', ' ', $page->getDBkey() ) ),
			'mci_page_title' => $page->getDBkey(),
			'mci_count' => $pageCount
		];
	}

	/**
	 * @param array $info
	 * @return void
	 */
	private function insert( array $info ) {
		$dbw = $this->lb->getConnectionRef( DB_PRIMARY );
		if ( !$dbw->tableExists( 'mws_category_index' ) ) {
			return;
		}
		$dbw->insert( 'mws_category_index', $info, __METHOD__, [ 'IGNORE' ] );
	}
}
