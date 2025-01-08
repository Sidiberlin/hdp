<?php

namespace MediaWiki\Extension\CollabPads\HookHandler;

use BlueSpice\SaferEdit\Hook\BSSaferEditMessage;
use Message;
use RequestContext;
use Title;
use Wikimedia\Rdbms\LoadBalancer;

class SaferEditMessage implements BSSaferEditMessage {

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
	public function onBSSaferEditMessage( Title $title, string &$message ): void {
		$editAction = RequestContext::getMain()->getRequest()->getText( 'veaction', '' );
		if ( $editAction ) {
			$message = '';
			return;
		}

		if ( $this->hasCollabPadsSession( $title ) ) {
			$message = $this->makeCollabPadsMessage( $title );
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

	/**
	 * @param Title $title
	 * @return string
	 */
	private function makeCollabPadsMessage( Title $title ): string {
		$collabPadsEditLink = $title->getFullURL( [ 'veaction' => 'collab-edit' ] );
		return Message::newFromKey( 'collabpads-bs-saferedit-editing', $collabPadsEditLink )->parse();
	}
}
