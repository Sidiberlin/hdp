<?php

namespace MWStake\MediaWiki\Component\CommonWebAPIs;

use ManualLogEntry;
use MediaWiki\Hook\AfterImportPageHook;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\Page\Hook\ArticleUndeleteHook;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use PageProps;
use Wikimedia\Rdbms\ILoadBalancer;

class TitleIndexUpdater implements
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
	 * @var PageProps
	 */
	private $pageProps;

	/**
	 * @param ILoadBalancer $lb
	 * @param PageProps $pageProps
	 */
	public function __construct( ILoadBalancer $lb, PageProps $pageProps ) {
		$this->lb = $lb;
		$this->pageProps = $pageProps;
	}

	/**
	 * @inheritDoc
	 */
	public function onPageSaveComplete(
		$wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
	) {
		$this->insert( $wikiPage->getTitle() );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		$this->delete( $old->getNamespace(), $old->getDBkey() );
		$this->insert( $new );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageDeleteComplete(
		ProperPageIdentity $page, Authority $deleter, string $reason, int $pageID,
		RevisionRecord $deletedRev, ManualLogEntry $logEntry, int $archivedRevisionCount
	) {
		$this->delete( $page->getNamespace(), $page->getDBkey() );
	}

	/**
	 * @inheritDoc
	 */
	public function onArticleUndelete( $title, $create, $comment, $oldPageId, $restoredPages ) {
		$this->insert( $title, $oldPageId );
	}

	/**
	 * @inheritDoc
	 */
	public function onAfterImportPage( $title, $foreignTitle, $revCount, $sRevCount, $pageInfo ) {
		$this->insert( $title );
	}

	/**
	 * @param PageIdentity $page
	 * @param int|null $forceId (optional)
	 *
	 * @return bool
	 */
	private function insert( PageIdentity $page, $forceId = null ) {
		$db = $this->lb->getConnection( DB_PRIMARY );
		if ( !$db->tableExists( 'mws_title_index' ) ) {
			return false;
		}
		if ( !$page->exists() ) {
			return false;
		}
		// Cheaper to delete and insert, then to check if it exists
		$db->delete(
			'mws_title_index',
			[
				'mti_page_id' => $forceId ?? $page->getId()
			],
			__METHOD__
		);

		return $db->insert(
			'mws_title_index',
			[
				'mti_page_id' => $forceId ?? $page->getId(),
				'mti_namespace' => $page->getNamespace(),
				'mti_title' => mb_strtolower( str_replace( '_', ' ', $page->getDBkey() ) ),
				'mti_displaytitle' => $this->getDisplayTitle( $page ),
			],
			__METHOD__,
			[ 'OVERWRITE' ]
		);
	}

	/**
	 * @param int $namespace
	 * @param string $title
	 *
	 * @return bool
	 */
	private function delete( int $namespace, string $title ) {
		$db = $this->lb->getConnection( DB_PRIMARY );
		$db = $this->lb->getConnection( DB_PRIMARY );
		if ( !$db->tableExists( 'mws_title_index' ) ) {
			return false;
		}
		return $db->delete(
			'mws_title_index',
			[
				'mti_namespace' => $namespace,
				'mti_title' => mb_strtolower( str_replace( '_', ' ', $title ) ),
			],
			__METHOD__
		);
	}

	/**
	 * @param PageIdentity $page
	 *
	 * @return string
	 */
	private function getDisplayTitle( PageIdentity $page ): string {
		$display = $this->pageProps->getProperties( $page, 'displaytitle' );
		if ( isset( $display[$page->getId()] ) ) {
			return mb_strtolower( str_replace( '_', ' ', $display[$page->getId()] ) );
		}
		return '';
	}
}
