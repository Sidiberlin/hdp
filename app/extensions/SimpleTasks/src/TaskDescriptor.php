<?php

namespace SimpleTasks;

use MediaWiki\Extension\UnifiedTaskOverview\ITaskDescriptor;
use MediaWiki\Language\Language;
use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;

class TaskDescriptor implements ITaskDescriptor {

	/** @var SimpleTask */
	protected $task;
	/** @var Language */
	private $language;

	/**
	 * @param SimpleTask $task
	 * @param Language $language
	 */
	public function __construct( SimpleTask $task, Language $language ) {
		$this->task = $task;
		$this->language = $language;
	}

	/**
	 * @return string
	 */
	public function getType(): string {
		return 'simple-tasks-task';
	}

	/**
	 * @return string
	 */
	public function getURL(): string {
		return $this->task->getChecklistItem()->getPage()->getLocalURL();
	}

	/**
	 * @return Message
	 */
	public function getHeader(): Message {
		return new RawMessage(
			$this->task->getText()
		);
	}

	/**
	 * @return Message
	 */
	public function getSubHeader(): Message {
		$due = $this->task->getDueDate();
		if ( !$due ) {
			return new RawMessage( '' );
		}
		return Message::newFromKey(
			'simple-tasks-task-duedate',
			$this->language->userDate( $due->format( 'YmdHid' ), $this->task->getUser() )
		);
	}

	/**
	 * @return Message
	 */
	public function getBody(): Message {
		return Message::newFromKey( 'simple-tasks-task-desc' );
	}

	/**
	 * @return int
	 */
	public function getSortKey(): int {
		return 2;
	}

	/**
	 * @return array
	 */
	public function getRLModules(): array {
		return [];
	}
}
