<?php

namespace BlueSpice\ReadConfirmation\Mechanism;

use BlueSpice\PageAssignments\AssignmentFactory;
use BlueSpice\PageAssignments\Data\Record;
use BlueSpice\PageAssignments\TitleTarget;
use BlueSpice\ReadConfirmation\Event\ConfirmationRemindEvent;
use BlueSpice\ReadConfirmation\Event\ConfirmationRequestEvent;
use BlueSpice\ReadConfirmation\IMechanism;
use Exception;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use MWStake\MediaWiki\Component\Events\Notifier;
use Wikimedia\Rdbms\LoadBalancer;

class NonMinorEdit implements IMechanism {

	/**
	 * @var LoadBalancer
	 */
	private $dbLoadBalancer;

	/**
	 * @var int|null
	 */
	private $revisionId = null;

	/**
	 * @var array
	 */
	private $enabledNamespaces;

	/**
	 *
	 * @var AssignmentFactory
	 */
	protected $assignmentFactory = null;

	/**
	 * @var Notifier
	 */
	protected $notifier;

	/** @var MediaWikiServices */
	protected $services = null;

	/**
	 * @return NonMinorEdit
	 */
	public static function factory() {
		global $wgNamespacesWithEnabledReadConfirmation;
		$services = MediaWikiServices::getInstance();
		$dbLoadBalancer = $services->getDBLoadBalancer();
		$assignmentFactory = $services->getService( 'BSPageAssignmentsAssignmentFactory' );
		$notifier = $services->getService( 'MWStake.Notifier' );
		return new self(
			$dbLoadBalancer,
			$wgNamespacesWithEnabledReadConfirmation,
			$assignmentFactory,
			$notifier
		);
	}

	/**
	 * NonMinorEdit constructor.
	 * @param LoadBalancer $dbLoadBalancer
	 * @param array $enabledNamespaces
	 * @param AssignmentFactory $assignmentFactory
	 * @param Notifier $notifier
	 */
	protected function __construct( $dbLoadBalancer, $enabledNamespaces, $assignmentFactory, Notifier $notifier ) {
		$this->dbLoadBalancer = $dbLoadBalancer;
		$this->enabledNamespaces = $enabledNamespaces;
		$this->assignmentFactory = $assignmentFactory;
		$this->notifier = $notifier;
		$this->services = MediaWikiServices::getInstance();
	}

	/**
	 * @return void
	 */
	public function wireUpNotificationTrigger() {
	}

	/**
	 * @return void
	 */
	public function autoNotify() {
		$titles = [];
		$recordSet = $this->assignmentFactory->getStore()->getReader()->read(
			new ReaderParams( [
				ReaderParams::PARAM_LIMIT => ReaderParams::LIMIT_INFINITE
			] )
		);
		foreach ( $recordSet->getRecords() as $record ) {
			$id = $record->get( Record::PAGE_ID, 0 );
			if ( $id < 1 || isset( $titles[$id] ) ) {
				continue;
			}
			$title = Title::newFromID( $id );
			if ( !$title || !$this->isNamespaceEnabled( $title ) ) {
				continue;
			}
			$titles[$id] = $title;
		}
		foreach ( $titles as $title ) {
			$this->notifyDaily( $title );
		}
	}

	/**
	 * @param Title $title
	 * @param User $userAgent
	 * @return User[]|bool
	 * @throws Exception
	 */
	public function notify( Title $title, User $userAgent ) {
		$target = $this->getTargetFromTitle( $title );
		if ( $target === false ) {
			return false;
		}

		$notifyUsers = $this->getNotifyUsers( $target );
		$this->notifier->emit( new ConfirmationRequestEvent( $userAgent, $title, $notifyUsers ) );

		return $notifyUsers;
	}

	/**
	 * @param Title $title
	 * @return bool
	 * @throws Exception
	 */
	private function notifyDaily( Title $title ) {
		$target = $this->getTargetFromTitle( $title );
		if ( $target === false ) {
			return false;
		}

		$notifyUsers = $this->getNotifyUsers( $target );
		$event = new ConfirmationRemindEvent( $title, $notifyUsers );
		$this->notifier->emit( $event );
		return true;
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param null $revId
	 * @return bool
	 */
	public function canConfirm( Title $title, User $user, $revId = null ) {
		$currentReadConfirmations = $this->getCurrentReadConfirmations(
			[ $user->getId() ],
			[ $title->getArticleID() ]
		);

		if ( isset( $currentReadConfirmations[ $title->getArticleID() ][ $user->getId() ] ) ) {
			return false;
		}

		$target = $this->getTargetFromTitle( $title );
		if ( $target === false || !$target->isUserAssigned( $user ) ) {
			return false;
			/**
			 * If the user is not assigned we bail out telling
			 * the caller that it already has been confirmed. This is not the
			 * truth and therefore not nice, but for the time being it is
			 * sufficient. Better solution would probably be to throw an
			 * exception
			 */
		}

		$revision = $this->getRecentRevisions( [ $title->getArticleID() ] );
		if ( count( $revision ) < 1 || !isset( $revision[ $title->getArticleID() ] ) ) {
			return false;
		}

		$this->revisionId = $revision[ $title->getArticleID() ];

		return true;
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param null $revId
	 * @return bool
	 */
	public function confirm( Title $title, User $user, $revId = null ) {
		if ( !$this->canConfirm( $title, $user, $revId ) ) {
			return false;
		}

		$row = [
			'rc_rev_id' => $this->revisionId,
			'rc_user_id' => $user->getId()
		];

		$this->dbLoadBalancer->getConnection( DB_PRIMARY )->delete(
			'bs_readconfirmation',
			$row,
			__METHOD__
		);
		$row[ 'rc_timestamp' ] = wfTimestampNow();
		$this->dbLoadBalancer->getConnection( DB_PRIMARY )->insert(
			'bs_readconfirmation',
			$row,
			__METHOD__
		);

		return true;
	}

	/**
	 * Gets THE LATEST read pages revisions for each user specified
	 *
	 * @param array $userIds List of user IDs
	 * @return array Array with such structure:
	 *  [
	 *    <user_id1> => [
	 *		 <page_id1> => <latest_read_revision1>,
	 * 		 <page_id2> => <latest_read_revision2>
	 *	  ],
	 * 	  <user_id2> => [
	 * 	  ...
	 * 	  ],
	 * 	  ...
	 * 	]
	 */
	private function getUserLatestReadRevisions( array $userIds ): array {
		if ( !$userIds ) {
			return [];
		}

		$res = $this->dbLoadBalancer->getConnection( DB_REPLICA )->select(
			[
				'revision',
				'bs_readconfirmation'
			],
			[
				'latest_rev' => 'MAX(rc_rev_id)',
				'rev_page',
				'rc_user_id'
			],
			[
				'rc_user_id' => $userIds
			],
			__METHOD__,
			[
				'GROUP BY' => [
					'rev_page',
					'rc_user_id'
				]
			],
			[
				'bs_readconfirmation' => [
					'INNER JOIN', 'rc_rev_id = rev_id'
				]
			]
		);

		$latestRevs = [];
		foreach ( $res as $row ) {
			$latestRevs[ (int)$row->rc_user_id ][ (int)$row->rev_page ] = (int)$row->latest_rev;
		}

		return $latestRevs;
	}

	/**
	 * @inheritDoc
	 */
	public function getLatestReadConfirmations( array $userIds = [] ): array {
		$userLatestReadRevisions = $this->getUserLatestReadRevisions( $userIds );

		$pageIds = [];
		foreach ( $userLatestReadRevisions as $latestReadRevisionData ) {
			foreach ( $latestReadRevisionData as $pageId => $latestReadRevisionId ) {
				$pageIds[$pageId] = true;
			}
		}
		$pageIds = array_keys( $pageIds );

		$recentRevisions = $this->getRecentRevisions( $pageIds );

		$readConfirmations = [];
		foreach ( $userLatestReadRevisions as $userId => $latestReadRevisionData ) {
			foreach ( $latestReadRevisionData as $pageId => $latestReadRevisionId ) {
				// In case if there are no major revisions of the page
				$recentRevisionId = 0;

				// There is some major revision of the page
				if ( isset( $recentRevisions[ $pageId ] ) ) {
					$recentRevisionId = $recentRevisions[ $pageId ];
				}

				$readConfirmations[$userId][$pageId] = [
					'latest_rev' => $recentRevisionId,
					'latest_read_rev' => $latestReadRevisionId
				];
			}
		}

		return $readConfirmations;
	}

	/**
	 * @param array $userIds
	 * @param array $pageIds
	 * @return array [ <page_id> => [ <user_id1>, <user_id2>, ...], ... ]
	 */
	public function getCurrentReadConfirmations( array $userIds = [], array $pageIds = [] ) {
		$currentReadConfirmations = [];
		$userReadRevisions = $this->getUserReadRevisions( $userIds );
		$recentRevisions = $this->getRecentRevisions( $pageIds );

		foreach ( $pageIds as $pageId ) {
			$reads = [];
			if (
				isset( $recentRevisions[ $pageId ] ) &&
				isset( $userReadRevisions[ $recentRevisions[ $pageId ] ] )
			) {
				$reads = $userReadRevisions[ $recentRevisions[ $pageId ] ];
			}
			$currentReadConfirmations[ $pageId ] = $reads;
		}
		return $currentReadConfirmations;
	}

	/**
	 * @param TitleTarget $target
	 * @return array
	 */
	private function getNotifyUsers( $target ) {
		$assignedUserIds = $target->getAssignedUserIDs();
		$currentReads = $this->getCurrentReadConfirmations(
			$assignedUserIds,
			[ $target->getTitle()->getArticleID() ]
		);

		$notReadUserIds = array_filter(
			$target->getAssignedUserIDs(),
			static function ( $e ) use( $target, $currentReads ) {
				return !isset( $currentReads[$target->getTitle()->getArticleID()][$e] );
			}
		);

		$notReadUsers = array_map( function ( $id ) {
			return $this->services->getUserFactory()->newFromId( $id );
		}, $notReadUserIds );

		return array_filter( $notReadUsers );
	}

	/**
	 * @param Title $title
	 * @return TitleTarget|bool
	 */
	private function getTargetFromTitle( $title ) {
		if ( $title instanceof Title === false ) {
			return false;
		}

		if ( !$this->isNamespaceEnabled( $title ) ) {
			return false;
		}

		/** @var TitleTarget $target */
		$target = $this->assignmentFactory->newFromTargetTitle( $title );
		if ( $target === false || empty( $target->getAssignedUserIDs() ) ) {
			return false;
		}

		return $target;
	}

	/**
	 * @param array $pageIds
	 * @return array in form [ <page_id> => <rev_id>, ... ]
	 */
	private function getRecentRevisions( $pageIds = [] ) {
		$recentData = [];

		if ( empty( $pageIds ) ) {
			return [];
		}

		$res = $this->dbLoadBalancer->getConnection( DB_REPLICA )->newSelectQueryBuilder()
			->from( 'page' )
			->select( [ 'page_id', 'page_latest' ] )
			->where( [ 'page_id' => $pageIds ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$recentData[ (int)$row->page_id ] = (int)$row->page_latest;
		}

		return $recentData;
	}

	/**
	 * @param array $userIds
	 * @return array Array with such structure:
	 * 	[
	 * 	  <rev_id> =>
	 *		[
	 * 			<user_id1> => <read_timestamp1>,
	 * 			<user_id2> => <read_timestamp2>,
	 * 			...
	 * 		],
	 * 	  ...
	 * 	]
	 */
	private function getUserReadRevisions( $userIds = [] ) {
		$conds = [];
		if ( !empty( $userIds ) ) {
			$conds['rc_user_id'] = $userIds;
		}
		$res = $this->dbLoadBalancer
			->getConnection( DB_REPLICA )
			->select( 'bs_readconfirmation', '*', $conds, __METHOD__ );

		$readRevisions = [];
		foreach ( $res as $row ) {
			$revId = (int)$row->rc_rev_id;
			if ( !isset( $readRevisions[ $revId ] ) ) {
				$readRevisions[ $revId ] = [];
			}
			$readRevisions[ $revId ][ (int)$row->rc_user_id ] = $row->rc_timestamp;
		}

		return $readRevisions;
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	private function isNamespaceEnabled( $title ) {
		if ( !$title instanceof Title ) {
			return true;
		}
		$ns = $title->getNamespace();
		if ( isset( $this->enabledNamespaces[$ns] ) && $this->enabledNamespaces[$ns] ) {
			return true;
		}
		return false;
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	public function mustRead( Title $title ) {
		if ( !$title instanceof Title ) {
			return false;
		}
		if ( !isset( $this->enabledNamespaces[$title->getNamespace()] )
			|| !$this->enabledNamespaces[$title->getNamespace()] ) {
			return false;
		}
		return true;
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @return RevisionRecord|null
	 */
	public function getLatestRevisionToConfirm( Title $title, User $user ): ?RevisionRecord {
		return $this->services->getRevisionLookup()->getRevisionByTitle( $title );
	}
}
