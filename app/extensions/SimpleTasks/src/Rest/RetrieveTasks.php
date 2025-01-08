<?php

namespace SimpleTasks\Rest;

use MediaWiki\Rest\SimpleHandler;
use SimpleTasks\SimpleTaskManager;

class RetrieveTasks extends SimpleHandler {

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
	public function run() {
		$tasks = $this->taskManager->query();
		return $this->getResponseFactory()->createJson( $tasks );
	}

}
