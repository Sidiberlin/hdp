<?php

namespace ChatBot\Rest;

use ChatBot\DeepsetApi\FeedbackApi;
use Exception;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class ChatFeedback extends SimpleHandler {

	/** @var FeedbackApi */
	private $feedbackApi;

	/**
	 *
	 * @param FeedbackApi $feedbackApi
	 */
	public function __construct( FeedbackApi $feedbackApi ) {
		$this->feedbackApi = $feedbackApi;
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function execute(): array {
		$body = $this->getValidatedBody();
		$validated = $this->getValidatedParams();
		$feedbackId = '';
		if ( isset( $validated['id'] ) ) {
			$feedbackId = $validated['id'];
		}

		try {
			return $this->feedbackApi->request( $feedbackId, $body['feedback'] );
		} catch ( Exception $e ) {
			return [
				'errors' => [ $e->getMessage() ]
			];
		}
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'id' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyParamSettings(): array {
		return [
			'sessionId' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'feedback' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_DEFAULT => -1
			],
		];
	}
}
