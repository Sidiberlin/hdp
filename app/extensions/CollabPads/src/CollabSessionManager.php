<?php

namespace MediaWiki\Extension\CollabPads;

use ObjectCacheFactory;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\Platform\ISQLPlatform;

class CollabSessionManager {

	/** @var IDatabase */
	private $dbr;

	/** @var IDatabase */
	private $dbw;

	/** @var IConnectionProvider */
	private $connectionProvider;

	/** @var ObjectCacheFactory */
	private $objectCacheFactory;

	/**
	 * @param IConnectionProvider $connectionProvider
	 * @param ObjectCacheFactory $objectCacheFactory
	 */
	public function __construct( IConnectionProvider $connectionProvider, ObjectCacheFactory $objectCacheFactory ) {
		$this->connectionProvider = $connectionProvider;
		$this->objectCacheFactory = $objectCacheFactory;
	}

	/**
	 * @return IDatabase
	 */
	private function getDBW() {
		$dbw = $this->dbw ??= $this->connectionProvider->getPrimaryDatabase();
		return $dbw;
	}

	/**
	 * @return IDatabase
	 */
	private function getDBR() {
		$dbr = $this->dbr ??= $this->connectionProvider->getReplicaDatabase();
		return $dbr;
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

		$objectCache = $this->objectCacheFactory->getLocalServerInstance();
		$fname = __METHOD__;

		return $objectCache->getWithSetCallback(
			$objectCache->makeKey( 'collabpads-getsession', $pageNamespace, $pageTitle ),
			$objectCache::TTL_SECOND,
			function () use ( $pageNamespace, $pageTitle, $fname ) {
				$dbr = $this->getDBR();

				$row = $dbr->newSelectQueryBuilder()
					->table( 'collabpad_session' )
					->field( ISQLPlatform::ALL_ROWS )
					->where( [
						's_page_namespace' => $pageNamespace,
						's_page_title' => $pageTitle
					] )
					->caller( $fname )
					->fetchRow();

				if ( !$row ) {
					return [];
				}

				return [
					'sessionId' => $row->s_id,
					'pageNamespace' => $row->s_page_namespace,
					'pageTitle' => $row->s_page_title,
					'owner' => $row->s_owner,
					'participants' => $row->s_participants
				];
			}
		);
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
			],
			__METHOD__
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
