<?php

namespace BlueSpice\ReadConfirmation\AttentionIndicator;

use BlueSpice\Discovery\AttentionIndicator;
use BlueSpice\Discovery\IAttentionIndicator;
use BlueSpice\ReadConfirmation\MechanismFactory;
use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\LoadBalancer;

class ReadConfirmation extends AttentionIndicator {

	/**
	 * @var MechanismFactory
	 */
	protected $mechanismFactory = null;

	/**
	 * @var LoadBalancer
	 */
	protected $lb = null;

	/**
	 * @var UserGroupManager
	 */
	protected $userGroupManager = null;

	/**
	 * @var TitleFactory
	 */
	protected $titleFactory = null;

	/**
	 * @var IDatabase
	 */
	private $dbr;

	/**
	 * @param string $key
	 * @param Config $config
	 * @param User $user
	 * @param MechanismFactory $mechanismFactory
	 * @param LoadBalancer $lb
	 * @param UserGroupManager $userGroupManager
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		string $key, Config $config, User $user,
		MechanismFactory $mechanismFactory, LoadBalancer $lb,
		UserGroupManager $userGroupManager, TitleFactory $titleFactory
	) {
		$this->mechanismFactory = $mechanismFactory;
		$this->lb = $lb;
		$this->dbr = $lb->getConnection( DB_REPLICA );
		$this->userGroupManager = $userGroupManager;
		$this->titleFactory = $titleFactory;
		parent::__construct( $key, $config, $user );
	}

	/**
	 * @param string $key
	 * @param Config $config
	 * @param User $user
	 * @param MediaWikiServices $services
	 * @param MechanismFactory|null $mechanismFactory
	 * @param LoadBalancer|null $lb
	 * @param UserGroupManager|null $userGroupManager
	 * @param TitleFactory|null $titleFactory
	 * @return IAttentionIndicator
	 */
	public static function factory(
		string $key, Config $config, User $user, MediaWikiServices $services,
		?MechanismFactory $mechanismFactory = null, ?LoadBalancer $lb = null,
		?UserGroupManager $userGroupManager = null, ?TitleFactory $titleFactory = null
	) {
		if ( !$mechanismFactory ) {
			$mechanismFactory = $services->getService( 'BSReadConfirmationMechanismFactory' );
		}
		if ( !$lb ) {
			$lb = $services->getDBLoadBalancer();
		}
		if ( !$userGroupManager ) {
			$userGroupManager = $services->getUserGroupManager();
		}
		if ( !$titleFactory ) {
			$titleFactory = $services->getTitleFactory();
		}
		return new static(
			$key,
			$config,
			$user,
			$mechanismFactory,
			$lb,
			$userGroupManager,
			$titleFactory
		);
	}

	/**
	 * @return array
	 */
	protected function getEnabledNamespaces(): array {
		$namespaces = $this->config->get( 'NamespacesWithEnabledReadConfirmation' );
		return is_array( $namespaces )
			? array_keys( array_filter(
				$namespaces,
				fn ( $enabled, $nsId ) => is_int( $nsId ) && $enabled === true,
				ARRAY_FILTER_USE_BOTH
			) ) : [];
	}

	/**
	 * @param array $conditions
	 * @return IResultWrapper
	 */
	private function selectPageIds( array $conditions ) {
		return $this->dbr->newSelectQueryBuilder()
			->fields( [ 'pa_page_id' ] )
			->table( 'bs_pageassignments' )
			->join( 'page', null, 'pa_page_id = page_id' )
			->where( array_merge( $conditions, [
				'page_namespace' => $this->getEnabledNamespaces()
			] ) )
			->distinct()
			->caller( __METHOD__ )
			->fetchResultSet();
	}

	/**
	 * @return int
	 */
	protected function doIndicationCount(): int {
		$count = 0;
		if ( $this->getEnabledNamespaces() === [] ) {
			return $count;
		}
		$cases = [
			[
				'pa_assignee_key' => [ $this->user->getName() ],
				'pa_assignee_type' => 'user'
			],
			[
				'pa_assignee_key' => $this->userGroupManager->getUserGroups( $this->user ),
				'pa_assignee_type' => 'group'
			],
			[ 'pa_assignee_type' => 'everyone' ]
		];
		$ids = [];
		foreach ( $cases as $conditions ) {
			$res = $this->selectPageIds( $conditions );
			foreach ( $res as $row ) {
				$ids[] = $row->pa_page_id;
			}
		}
		$ids = array_unique( $ids );

		if ( empty( $ids ) ) {
			return $count;
		}
		$readConfirmations = $this->mechanismFactory->getMechanismInstance()
			->getLatestReadConfirmations( [ $this->user->getId() ] );

		$userReadConfirmations = [];
		if ( !empty( $readConfirmations[$this->user->getId()] ) ) {
			$userReadConfirmations = $readConfirmations[$this->user->getId()];
		}

		foreach ( $ids as $id ) {
			if ( empty( $userReadConfirmations[$id]['latest_read_rev'] ) ) {
				$count++;
				continue;
			}
			if ( $userReadConfirmations[$id]['latest_rev']
				== $userReadConfirmations[$id]['latest_read_rev'] ) {
				continue;
			}
			$title = $this->titleFactory->newFromID( $id );
			if ( !$title || !$title->exists() ) {
				continue;
			}
			if (
				!$this->mechanismFactory->getMechanismInstance()->canConfirm(
					$title, $this->user, $title->getLatestRevID()
				)
			) {
				continue;
			}
			$count++;
		}

		return $count;
	}

}
