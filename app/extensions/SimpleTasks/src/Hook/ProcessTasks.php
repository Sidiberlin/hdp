<?php

namespace SimpleTasks\Hook;

use MediaWiki\Extension\Checklists\ChecklistManager;
use MediaWiki\Extension\Checklists\Hook\ChecklistsItemsCreatedHook;
use MediaWiki\Extension\Checklists\Hook\ChecklistsItemsDeletedHook;
use MediaWiki\Extension\Checklists\Hook\ChecklistsItemsUpdatedHook;
use SimpleTasks\SimpleTask;
use SimpleTasks\SimpleTaskManager;

class ProcessTasks implements
	ChecklistsItemsCreatedHook,
	ChecklistsItemsUpdatedHook,
	ChecklistsItemsDeletedHook
{

	/** @var SimpleTaskManager */
	private $taskManager;

	/**
	 * @param SimpleTaskManager $taskManager
	 */
	public function __construct( SimpleTaskManager $taskManager ) {
		$this->taskManager = $taskManager;
	}

	/**
	 * @inheritDoc
	 */
	public function onChecklistsItemsCreated( array $items, ChecklistManager $checklistManager ) {
		$this->persistsTasksFromCheckboxes( $items );
	}

	/**
	 * @inheritDoc
	 */
	public function onChecklistsItemsDeleted( array $items, ChecklistManager $checklistManager ) {
		foreach ( $items as $checkbox ) {
			$this->taskManager->delete( $checkbox->getId() );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onChecklistsItemsUpdated( array $items, ChecklistManager $checklistManager ) {
		$this->persistsTasksFromCheckboxes( $items );
	}

	/**
	 * @param array $checkboxes
	 *
	 * @return void
	 */
	private function persistsTasksFromCheckboxes( array $checkboxes ) {
		foreach ( $checkboxes as $checkbox ) {
			$task = $this->taskManager->processTask( $checkbox );
			if ( $task instanceof SimpleTask ) {
				$this->taskManager->persist( $task );
			}
		}
	}
}
