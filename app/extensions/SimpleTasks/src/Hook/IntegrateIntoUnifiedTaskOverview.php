<?php

namespace SimpleTasks\Hook;

use Language;
use MediaWiki\Extension\UnifiedTaskOverview\Hook\GetTaskDescriptors;
use SimpleTasks\SimpleTaskManager;
use SimpleTasks\TaskDescriptor;
use User;

class IntegrateIntoUnifiedTaskOverview implements GetTaskDescriptors {

	/** @var SimpleTaskManager */
	private $taskManager;

	/** @var Language */
	private $language;

	/**
	 * @param SimpleTaskManager $taskManager
	 * @param Language $language
	 */
	public function __construct( SimpleTaskManager $taskManager, Language $language ) {
		$this->taskManager = $taskManager;
		$this->language = $language;
	}

	/**
	 * @inheritDoc
	 */
	public function onUnifiedTaskOverviewGetTaskDescriptors( &$descriptors, User $user ) {
		$tasks = $this->taskManager->forUser( $user )->completed( false )->query();
		foreach ( $tasks as $task ) {
			$descriptors[] = new TaskDescriptor( $task, $this->language );
		}
	}
}
