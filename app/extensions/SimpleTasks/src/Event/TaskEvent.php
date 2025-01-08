<?php

namespace SimpleTasks\Event;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\PriorityEvent;
use MWStake\MediaWiki\Component\Events\TitleEvent;
use SimpleTasks\SimpleTask;

class TaskEvent extends TitleEvent implements PriorityEvent {
	/** @var UserIdentity */
	private $assignedUser;
	/** @var string */
	private $text;

	/**
	 * @param SimpleTask $task
	 */
	public function __construct( SimpleTask $task ) {
		parent::__construct( $task->getChecklistItem()->getAuthor(), $task->getChecklistItem()->getPage() );
		$this->assignedUser = $task->getUser();
		$this->text = $task->getText();
	}

	/**
	 * @inheritDoc
	 */
	public function getKey(): string {
		return 'simple-tasks-task';
	}

	/**
	 * @inheritDoc
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'simple-tasks-notif-key' );
	}

	/**
	 * @inheritDoc
	 */
	public function getPresetSubscribers(): array {
		return [ $this->assignedUser ];
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage( IChannel $forChannel ): Message {
		return Message::newFromKey( 'simple-tasks-notif-message' )->params(
			$this->text
		);
	}

	/**
	 * @inheritDoc
	 */
	public function hasPriorityOver(): array {
		return [ 'at-mentions-mention' ];
	}

	/**
	 * @inheritDoc
	 */
	public static function getArgsForTesting(
		UserIdentity $agent, MediaWikiServices $services, array $extra = []
	): array {
		return [];
	}
}
