<?php

namespace SimpleTasks;

use AtMentions\MentionParser;
use DateTime;
use MediaWiki\Extension\Checklists\ChecklistItem;
use MediaWiki\Extension\Checklists\ChecklistManager;
use MediaWiki\Extension\DateTimeTools\DateTimeParser;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\Notifier;
use RefreshTasks;
use SimpleTasks\Event\TaskEvent;
use Title;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class SimpleTaskManager {

	/** @var ChecklistManager */
	private $checklistManager;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var UserFactory */
	private $userFactory;

	/** @var MentionParser */
	private $mentionParser;

	/** @var DateTimeParser */
	private $dateTimeParser;

	/** @var Notifier */
	private $notifier;

	/** @var array */
	private $userCondition = [];

	/** @var array */
	private $namespaceCondition = [];

	/** @var array */
	private $completedCondition = [];

	/** @var array */
	private $conds = [];

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param ChecklistManager $checklistManager
	 * @param UserFactory $userFactory
	 * @param MentionParser $mentionParser
	 * @param DateTimeParser $dateTimeParser
	 * @param Notifier $notifier
	 */
	public function __construct(
		ILoadBalancer $loadBalancer, ChecklistManager $checklistManager, UserFactory $userFactory,
		MentionParser $mentionParser, DateTimeParser $dateTimeParser, Notifier $notifier
	) {
		$this->loadBalancer = $loadBalancer;
		$this->checklistManager = $checklistManager;
		$this->userFactory = $userFactory;
		$this->mentionParser = $mentionParser;
		$this->dateTimeParser = $dateTimeParser;
		$this->notifier = $notifier;
	}

	/**
	 * Create task from the checklist item, if applicable
	 * @param ChecklistItem $check
	 *
	 * @return SimpleTask|null if not a task
	 */
	public function processTask( ChecklistItem $check ): ?SimpleTask {
		if ( !$check->getId() ) {
			return null;
		}
		if ( empty( $check->getText() ) ) {
			return null;
		}
		$text = $check->getText();
		$mention = $this->parseMention( $text );
		if ( !$mention ) {
			return null;
		}
		$date = $this->parseDueDate( $text );
		$this->normalizeText( $text );
		return new SimpleTask( $check, $text, $mention, $date );
	}

	/**
	 * @param UserIdentity $user
	 *
	 * @return $this
	 */
	public function forUser( UserIdentity $user ): SimpleTaskManager {
		if ( !$user->isRegistered() ) {
			return $this;
		}
		$this->userCondition[] = $user->getId();
		return $this;
	}

	/**
	 * @param Title $title
	 *
	 * @return $this
	 */
	public function forTitle( Title $title ): SimpleTaskManager {
		if ( !$title->exists() ) {
			return $this;
		}
		$this->conds['ci_page'] = $title->getArticleID();
		return $this;
	}

	/**
	 * @param int $namespace
	 *
	 * @return $this
	 */
	public function forNamespace( $namespace ): SimpleTaskManager {
		$this->namespaceCondition[] = $namespace;
		return $this;
	}

	/**
	 * @param string $id
	 *
	 * @return $this
	 */
	public function id( string $id ): SimpleTaskManager {
		$this->conds['st_check_id'] = $id;
		return $this;
	}

	/**
	 * @param bool $completed
	 *
	 * @return SimpleTaskManager
	 */
	public function completed( $completed = true ): SimpleTaskManager {
		$this->completedCondition[] = $completed ? 1 : 0;
		return $this;
	}

	/**
	 * @param DateTime $date
	 * @param string $operator
	 * @return SimpleTaskManager
	 */
	public function forDate( DateTime $date, ?string $operator = 'eq' ): SimpleTaskManager {
		switch ( $operator ) {
			case 'eq':
				$this->conds['st_duedate'] = $date->format( 'YmdHis' );
				break;
			case 'lt':
				$this->conds[] = 'st_duedate <= ' . $date->format( 'YmdHis' );
				break;
			case 'gt':
				$this->conds[] = 'st_duedate >= ' . $date->format( 'YmdHis' );
				break;
		}

		return $this;
	}

	/**
	 *
	 * @param IDatabase $db
	 * @return void
	 */
	private function formatConditions( IDatabase $db ) {
		if ( !empty( $this->userCondition ) ) {
			$this->conds[] = 'st_user IN (' . $db->makeList( $this->userCondition ) . ')';
			$this->userCondition = [];
		}
		if ( !empty( $this->namespaceCondition ) ) {
			$this->conds[] = 'page_namespace IN (' . $db->makeList( $this->namespaceCondition ) . ')';
			$this->namespaceCondition = [];
		}
		if ( !empty( $this->completedCondition ) ) {
			$this->conds[] = 'st_completed IN (' . $db->makeList( $this->completedCondition ) . ')';
			$this->completedCondition = [];
		}
	}

	/**
	 * @param array|null $conds
	 *
	 * @return array
	 */
	public function query( ?array $conds = [] ): array {
		if ( !$this->isInitialized() ) {
			return [];
		}
		$db = $this->loadBalancer->getConnection( ILoadBalancer::DB_REPLICA );
		$this->formatConditions( $db );
		$conds = array_merge( $conds, $this->conds );

		$this->conds = [];
		$res = $db->select(
			[ 'simple_tasks', 'checklist_items', 'page' ],
			[ 'st_check_id', 'st_user', 'st_duedate', 'ci_page', 'st_text', 'st_completed', 'page_namespace' ],
			$conds,
			__METHOD__,
			[],
			[
				'checklist_items' => [ 'INNER JOIN', 'ci_id = st_check_id' ],
				'page' => [ 'INNER JOIN', 'page_id = ci_page' ]
			]
		);
		$tasks = [];
		foreach ( $res as $row ) {
			$checklist = $this->checklistManager->getStore()->id( $row->st_check_id )->query();
			if ( empty( $checklist ) ) {
				continue;
			}
			$check = $checklist[0];
			$dueDate = DateTime::createFromFormat( 'YmdHis', $row->st_duedate );
			$tasks[] = new SimpleTask(
				$check,
				$row->st_text,
				$this->userFactory->newFromId( intval( $row->st_user ) ),
				$dueDate ?: null,
			);
		}
		return $tasks;
	}

	/**
	 * @param SimpleTask $task
	 *
	 * @return bool
	 */
	public function persist( SimpleTask $task ): bool {
		if ( !$this->isInitialized() ) {
			return true;
		}
		$existing = $this->id( $task->getChecklistItem()->getId() )->query();
		if ( empty( $existing ) ) {
			$res = $this->insert( $task );
			$this->notify( $task );
			return $res;
		} else {
			$res = $this->update( $task, $existing[0] );
			if ( $this->isAssigneeChanged( $task, $existing[0] ) ) {
				$this->notify( $task );
			}
			return $res;
		}
	}

	/**
	 * @param RefreshTasks|null $maintenance
	 *
	 * @return void
	 */
	public function refreshAll( ?RefreshTasks $maintenance ) {
		if ( !$this->isInitialized() ) {
			return;
		}
		if ( $maintenance ) {
			$maintenance->outputText( "Refreshing all simple tasks...\n" );
		}
		$this->truncate();
		$checkboxes = $this->checklistManager->getStore()->query();
		if ( $maintenance ) {
			$maintenance->outputText( "Found " . count( $checkboxes ) . " checkboxes. Parsing tasks...\n" );
		}
		foreach ( $checkboxes as $checkbox ) {
			$task = $this->processTask( $checkbox );
			if ( !$task ) {
				continue;
			}
			$this->persist( $task );
		}
		if ( $maintenance ) {
			$maintenance->outputText( "Done!\n" );
		}
	}

	/**
	 * @param SimpleTask $task
	 *
	 * @throws \Exception
	 */
	private function notify( SimpleTask $task ) {
		// Notifications
		$taskNotification = new TaskEvent( $task );
		$this->notifier->emit( $taskNotification );
	}

	/**
	 * @param SimpleTask $task
	 * @param SimpleTask $existing
	 *
	 * @return bool
	 */
	private function isAssigneeChanged( SimpleTask $task, SimpleTask $existing ): bool {
		return $task->getUser()->getName() !== $existing->getUser()->getName();
	}

	/**
	 * @param SimpleTask $new
	 * @param SimpleTask $old
	 *
	 * @return bool
	 */
	private function update( SimpleTask $new, SimpleTask $old ): bool {
		if ( $old->getUser()->getId() !== $new->getUser()->getId() ) {
			// Asssignee changed
			$this->delete( $old->getChecklistItem()->getId() );
			return $this->insert( $new );
		}
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		return $dbw->update(
			'simple_tasks',
			[
				'st_duedate' => $new->getDueDate() ? $dbw->timestamp( $new->getDueDate()->format( 'YmdHis' ) ) : null,
				'st_text' => $new->getText(),
				'st_completed' => $new->isCompleted() ? 1 : 0,
			],
			[ 'st_check_id' => $new->getChecklistItem()->getId() ],
			__METHOD__
		);
	}

	/**
	 * @param SimpleTask $task
	 *
	 * @return bool
	 */
	private function insert( SimpleTask $task ): bool {
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		return $dbw->insert(
			'simple_tasks',
			[
				'st_check_id' => $task->getChecklistItem()->getId(),
				'st_user' => $task->getUser()->getId(),
				'st_duedate' => $task->getDueDate() ? $dbw->timestamp( $task->getDueDate()->format( 'YmdHis' ) ) : null,
				'st_text' => $task->getText(),
				'st_completed' => $task->isCompleted() ? 1 : 0,
			],
			__METHOD__
		);
	}

	/**
	 * @param string $id
	 *
	 * @return bool
	 */
	public function delete( string $id ): bool {
		if ( !$this->isInitialized() ) {
			return true;
		}
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		return $dbw->delete(
			'simple_tasks',
			[ 'st_check_id' => $id ],
			__METHOD__
		);
	}

	/**
	 * @return bool
	 */
	private function truncate(): bool {
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		return $dbw->delete(
			'simple_tasks',
			'*',
			__METHOD__
		);
	}

	/**
	 * @param string &$line
	 *
	 * @return UserIdentity|null
	 */
	private function parseMention( string &$line ): ?UserIdentity {
		$mentions = $this->mentionParser->parse( $line );
		if ( empty( $mentions ) ) {
			return null;
		}
		$line = str_replace( $mentions[0]['text'], '', $line );
		return $mentions[0]['user'];
	}

	/**
	 * @param string &$line
	 *
	 * @return DateTime|null
	 */
	private function parseDueDate( string &$line ): ?DateTime {
		$dates = $this->dateTimeParser->parse( $line );
		if ( empty( $dates ) ) {
			return null;
		}
		$line = str_replace( $dates[0]['text'], '', $line );
		return $dates[0]['datetime'];
	}

	/**
	 * @param string &$text
	 *
	 * @return void
	 */
	private function normalizeText( string &$text ) {
		// Trim and remove any double spaces
		$text = trim( $text );
		$text = preg_replace( '/\s+/', ' ', $text );
	}

	/**
	 * @return bool
	 */
	private function isInitialized(): bool {
		return !defined( 'MEDIAWIKI_INSTALL' ) && !defined( 'MW_UPDATER' );
	}
}
