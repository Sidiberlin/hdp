<?php

namespace SimpleTasks\Rest;

use MediaWiki\Extension\Checklists\ChecklistManager;
use MediaWiki\Rest\SimpleHandler;
use SimpleTasks\SimpleTask;
use SimpleTasks\SimpleTaskManager;
use Wikimedia\ParamValidator\ParamValidator;

class UpdateStatus extends SimpleHandler {

	/** @var SimpleTaskManager */
	private $taskManager;
	/**
	 * @var ChecklistManager
	 */
	private $checklistManager;

	/**
	 * @param SimpleTaskManager $taskManager
	 * @param ChecklistManager $checklistManager
	 */
	public function __construct( SimpleTaskManager $taskManager, ChecklistManager $checklistManager ) {
		$this->taskManager = $taskManager;
		$this->checklistManager = $checklistManager;
	}

	/**
	 * @inheritDoc
	 */
	public function run() {
		$validated = $this->getValidatedParams();
		$tasks = $this->taskManager->id( $validated['id'] )->query();
		if ( !$tasks ) {
			return $this->getResponseFactory()->createHttpError( 404, [ 'Task not found' ] );
		}
		/** @var SimpleTask $task */
		$task = $tasks[0];
		$check = $task->getChecklistItem();
		// TODO: Check-multi-value: Maybe offer more values
		$check->setValue( $validated['status'] === 'completed' ? 'checked' : '' );
		if ( !$this->checklistManager->getStore()->persist( $check ) ) {
			return $this->getResponseFactory()->createHttpError( 500, [ 'Failed to update task' ] );
		}
		return $this->getResponseFactory()->create();
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		return [
			'id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'status' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => '',
			]
		];
	}

}
