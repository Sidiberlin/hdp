<?php

namespace SimpleTasks\Rest;

use MediaWiki\Rest\SimpleHandler;
use SimpleTasks\SimpleTaskManager;
use Wikimedia\ParamValidator\ParamValidator;

class RetrieveTask extends SimpleHandler {

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
		$validated = $this->getValidatedParams();
		$tasks = $this->taskManager->id( $validated['id'] )->query();
		if ( !$tasks ) {
			return $this->getResponseFactory()->createHttpError( 404, [ 'Task not found' ] );
		}
		return $this->getResponseFactory()->createJson( $tasks[0] );
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
		];
	}

}
