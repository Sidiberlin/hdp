<?php

namespace BlueSpice\ReadConfirmation\AttentionIndicator;

use BlueSpice\Discovery\AttentionIndicator;
use BlueSpice\Discovery\IAttentionIndicator;
use BlueSpice\ReadConfirmation\MechanismFactory;
use Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserGroupManager;
use TitleFactory;
use User;
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
	 * @param string $key
	 * @param Config $config
	 * @param User $user
	 * @param MechanismFactory $mechanismFactory
	 * @param LoadBalancer $lb
	 * @param UserGroupManager $userGroupManager
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( string $key, Config $config, User $user,
		MechanismFactory $mechanismFactory, LoadBalancer $lb,
		UserGroupManager $userGroupManager, TitleFactory $titleFactory ) {
		$this->mechanismFactory = $mechanismFactory;
		$this->lb = $lb;
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
	public static function factory( string $key, Config $config, User $user,
		MediaWikiServices $services, MechanismFactory $mechanismFactory = null,
		LoadBalancer $lb = null, UserGroupManager $userGroupManager = null,
		TitleFactory $titleFactory = null ) {
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
	 * @return int
	 */
	protected function doIndicationCount(): int {
		$count = 0;
		$userGroups = $this->userGroupManager->getUserGroups( $this->user );
		$ids = [];
		$res = $this->lb->getConnection( DB_REPLICA )->select(
			'bs_pageassignments',
			[ 'pa_page_id' ],
			[ 'pa_assignee_key' => array_merge( [ $this->user->getName() ], $userGroups ) ],
			__METHOD__,
			[ 'DISTINCT' ]
		);
		foreach ( $res as $row ) {
			$ids[] = $row->pa_page_id;
		}
		$res = $this->lb->getConnection( DB_REPLICA )->select(
			'bs_pageassignments',
			[ 'pa_page_id' ],
			[ 'pa_assignee_type' => 'everyone' ],
			__METHOD__
		);
		foreach ( $res as $row ) {
			$ids[] = $row->pa_page_id;
		}
		array_unique( $ids );

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
			$title = $this->titleFactory->newFromID( $id );
			if ( !$title || !$title->exists() ) {
				continue;
			}
			if ( !$this->mechanismFactory->getMechanismInstance()->mustRead( $title ) ) {
				continue;
			}
			if ( empty( $userReadConfirmations[$id]['latest_read_rev'] ) ) {
				$count++;
				continue;
			}
			if ( $userReadConfirmations[$id]['latest_rev']
				== $userReadConfirmations[$id]['latest_read_rev'] ) {
				continue;
			}
			$count++;
		}

		return $count;
	}

}
