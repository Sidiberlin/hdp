<?php

namespace MediaWiki\Extension\CollabPads;

use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class CollabSessionManager {

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
	 * @param int $pageNamespace
	 * @param string $pageTitle
	 * @return array
	 */
	public function getSession( int $pageNamespace, string $pageTitle ) {
		if ( defined( 'MW_UPDATER' ) || defined( 'MEDIAWIKI_INSTALL' ) ) {
			return [];
		}
		$res = $this->getDBR()->select(
			'collabpad_session',
			[ 's_page_namespace', 's_page_title', 's_id', 's_owner', 's_participants' ],
			[ 's_page_title' => $pageTitle, 's_page_namespace' => $pageNamespace ],
			__METHOD__
		);
		$session = [];
		foreach ( $res as $row ) {
			$session['sessionId'] = $row->s_id;
			$session['pageNamespace'] = $row->s_page_namespace;
			$session['pageTitle'] = $row->s_page_title;
			$session['owner'] = $row->s_owner;
			$session['participants'] = $row->s_participants;
		}
		return $session;
	}

	/**
	 * @param array $participants
	 * @param int $sessionId
	 */
	public function setParticipants( array $participants, int $sessionId ) {
		$res = $this->getDBW()->update(
			'collabpad_session',
			[ 's_participants' => serialize( $participants ) ],
			[ 's_id' => $sessionId ],
			__METHOD__
		);
	}

	/**
	 * @param int $pageNamespace
	 * @param string $pageTitle
	 * @return array
	 */
	public function getParticipants( int $pageNamespace, string $pageTitle ) {
		$res = $this->getDBR()->select(
			'collabpad_session',
			[ 's_participants' ],
			[ 's_page_title' => $pageTitle, 's_page_namespace' => $pageNamespace ],
			__METHOD__
		);
		foreach ( $res as $row ) {
			return unserialize( $row->s_participants );
		}

		return [];
	}

	/**
	 * @param int $pageNamespace
	 * @param string $pageTitle
	 * @param string $user
	 * @param array $participants
	 */
	public function insertSession( int $pageNamespace, string $pageTitle,
			string $user, array $participants ) {
		$this->getDBW()->insert(
			'collabpad_session',
			[
				's_page_namespace' => $pageNamespace,
				's_page_title' => $pageTitle,
				's_owner' => $user,
				's_participants' => serialize( $participants )
			]
		);
	}

	/**
	 * @param int $pageNamespace
	 * @param string $pageTitle
	 * @return array
	 */
	public function getVisibilitySettings( int $pageNamespace, string $pageTitle ) {
		$res = $this->getDBR()->select(
			'collabpad_session',
			's_owner',
			[ 's_page_title' => $pageTitle, 's_page_namespace' => $pageNamespace ],
			__METHOD__
		);

		$permission = [];
		foreach ( $res as $row ) {
			$permission['owner'] = $row->s_owner;
		}
		return $permission;
	}

	/**
	 * @param int $pageNamespace
	 * @param string $pageTitle
	 * @return array
	 */
	public function getSessionExistsByName( int $pageNamespace, string $pageTitle ) {
		$res = $this->getDBR()->select(
			'collabpad_session',
			[ 's_owner' ],
			[ 's_page_title' => $pageTitle, 's_page_namespace' => $pageNamespace ],
			__METHOD__
		);

		$permission = [];
		foreach ( $res as $row ) {
			$permission['owner'] = $row->s_owner;
		}
		return $permission;
	}

	/**
	 * @param int $pageNamespace
	 * @param string $pageTitle
	 * @return array
	 */
	public function getSessionByName( int $pageNamespace, string $pageTitle ) {
		$res = $this->getDBR()->select(
			'collabpad_session',
			[ 's_page_title' ],
			[
				's_page_namespace' => $pageNamespace,
				's_page_title' => $pageTitle
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
		 * @param int $pageNamespace
		 * @param string $pageTitle
		 * @return array
		 */
	public function getSessionOwner( int $pageNamespace, string $pageTitle ) {
		$res = $this->getDBR()->select(
			'collabpad_session',
			[ 's_owner' ],
			[ 's_page_title' => $pageTitle, 's_page_namespace' => $pageNamespace ],
			__METHOD__
		);
		foreach ( $res as $row ) {
			return $row->s_owner;
		}

		return [];
	}

	/**
	 * @param int $pageNamespace
	 * @param string $pageTitle
	 */
	public function deleteSession( int $pageNamespace, string $pageTitle ) {
		$this->getDBW()->delete(
			'collabpad_session',
			[ 's_page_title' => $pageTitle, 's_page_namespace' => $pageNamespace ],
			__METHOD__
		);
	}

	/**
	 * @param string $owner
	 * @return array $result
	 */
	public function getAllSessions( string $owner ) {
		$whereCondition = [];
		if ( $owner !== '*' ) {
			$whereCondition[ 's_owner' ] = $owner;
		}

		$res = $this->getDBR()->select(
			'collabpad_session',
			[ '*' ],
			$whereCondition,
			__METHOD__
		);
		$result = [];
		foreach ( $res as $r ) {
			$r->s_participants = unserialize( $r->s_participants );
			$result[] = $r;
		}
		return $result;
	}
}
