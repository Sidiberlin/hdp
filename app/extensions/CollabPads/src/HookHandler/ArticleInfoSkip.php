<?php

namespace MediaWiki\Extension\CollabPads\HookHandler;

use BlueSpice\ArticleInfo\Hook\BSArticleInfoSkipHook;
use Title;
use Wikimedia\Rdbms\LoadBalancer;

class ArticleInfoSkip implements BSArticleInfoSkipHook {

	/** @var LoadBalancer */
	private $loadBalancer;

	/**
	 * @param LoadBalancer $loadBalancer
	 */
	public function __construct( $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @inheritDoc
	 */
	public function onBSArticleInfoSkip( Title $title, bool &$skip ): void {
		if ( $this->hasCollabPadsSession( $title ) ) {
			$skip = true;
		}
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	private function hasCollabPadsSession( Title $title ): bool {
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );
		$resRowCount = $dbr->newSelectQueryBuilder()
			->select( 's_id' )
			->from( 'collabpad_session' )
			->where( [
				"s_page_title" => $title->getDBkey(),
				"s_page_namespace" => $title->getNamespace(),
			] )
			->caller( __METHOD__ )
			->fetchRowCount();

		if ( $resRowCount ) {
			return true;
		}

		return false;
	}
}
