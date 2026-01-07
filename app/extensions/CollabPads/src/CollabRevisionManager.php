<?php

namespace MediaWiki\Extension\CollabPads;

use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class CollabRevisionManager {

	/**
	 * @var IDatabase
	 */
	private $dbr = null;

	/**
	 * @var IDatabase
	 */
	private $dbw = null;

	/**
	 * @var ILoadBalancer
	 */
	private $lb = null;

	/**
	 * @param ILoadBalancer $lb
	 */
	public function __construct( ILoadBalancer $lb ) {
		$this->lb = $lb;
	}

	/**
	 * @return IDatabase
	 */
	private function getDBW() {
		if ( $this->dbw === null ) {
			$this->dbw = $this->lb->getConnection( DB_PRIMARY );
		}
		return $this->dbw;
	}

	/**
	 * @return IDatabase
	 */
	private function getDBR() {
		if ( $this->dbr === null ) {
			$this->dbr = $this->lb->getConnection( DB_REPLICA );
		}
		return $this->dbr;
	}

	/**
	 * @param int $revisionId
	 * @return array
	 */
	public function getSessionByRevId( int $revisionId ) {
		$res = $this->getDBR()->select(
			'collabpad_revision',
			[ '*' ],
			[ 'sr_rev_id' => $revisionId ],
			__METHOD__
		);
		$session = [];
		foreach ( $res as $row ) {
			$session['revisionId'] = $row->sr_rev_id;
			$session['sessionId'] = $row->sr_sesion_id;
			$session['paheNamespace'] = $row->sr_page_namespace;
			$session['pageTitle'] = $row->sr_page_title;
			$session['owner'] = $row->sr_owner;
			$session['actor'] = $row->sr_rev_actor;
			$session['participants'] = unserialize( $row->sr_participants );
		}
		return $session;
	}

	/**
	 * @param int $revisionId
	 * @return array
	 */
	public function getParticipants( int $revisionId ) {
		$res = $this->getDBR()->select(
			'collabpad_revision',
			[ 'sr_participants' ],
			[ 'sr_rev_id' => $revisionId ],
			__METHOD__
		);
		foreach ( $res as $row ) {
			return unserialize( $row->sr_participants );
		}

		return [];
	}

	/**
	 * @param int $revisionId
	 * @param int $sessionId
	 * @param int $pageNamespace
	 * @param string $pageTitle
	 * @param string $owner
	 * @param int $revisionActor
	 * @param string $participants
	 */
	public function insertSession(
		int $revisionId, int $sessionId, int $pageNamespace,
		string $pageTitle, string $owner, int $revisionActor, string $participants
	) {
		$res = $this->getDBW()->insert(
			'collabpad_revision',
			[
				'sr_rev_id' => $revisionId,
				'sr_session_id' => $sessionId,
				'sr_page_namespace' => $pageNamespace,
				'sr_page_title' => $pageTitle,
				'sr_owner' => $owner,
				'sr_rev_actor' => $revisionActor,
				'sr_participants' => $participants
			],
			__METHOD__
		);
	}

	/**
	 * @param int $pageNamespace
	 * @param string $pageTitle
	 * @return array
	 */
	public function getSessionByName( int $pageNamespace, string $pageTitle ) {
		$res = $this->getDBR()->select(
			'collabpad_revision',
			[ '*' ],
			[
				'sr_page_namespace' => $pageNamespace,
				'sr_page_title' => $pageTitle
			],
			__METHOD__
		);
		$result = [];
		foreach ( $res as $r ) {
			$result[] = $r;
		}
		return $result;
	}

	/**
	 * @param int $revisionId
	 * @return array
	 */
	public function getSessionOwner( int $revisionId ) {
		$res = $this->getDBR()->select(
			'collabpad_revision',
			[ 'sr_owner' ],
			[ 'sr_rev_id' => $revisionId ],
			__METHOD__
		);
		foreach ( $res as $row ) {
			return $row->sr_owner;
		}

		return [];
	}

	/**
	 * @param int $revisionId
	 * @return array
	 */
	public function getSessionActor( int $revisionId ) {
		$res = $this->getDBR()->select(
			'collabpad_revision',
			[ 'sr_rev_actor' ],
			[ 'sr_rev_id' => $revisionId ],
			__METHOD__
		);
		foreach ( $res as $row ) {
			return $row->sr_rev_actor;
		}

		return [];
	}
}
