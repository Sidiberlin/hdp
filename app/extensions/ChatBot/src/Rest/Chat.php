<?php

namespace ChatBot\Rest;

use ChatBot\DeepsetApi\ChatApi;
use Exception;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class Chat extends SimpleHandler {
	/** @var ChatApi */
	private ChatApi $chatApi;

	/**
	 * @param ChatApi $chatApi
	 */
	public function __construct( ChatApi $chatApi ) {
		$this->chatApi = $chatApi;
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function execute(): array {
		$params = $this->getValidatedParams();
		$query = $params['query'];
		$sessionId = $params['sessionId'];
		$followUpType = $params['followUpType'];

		try {
			$this->chatApi->request( $query, $sessionId, $followUpType );

			return [];
		} catch ( Exception $e ) {
			return [
				'errors' => [ $e->getMessage() ],
			];
		}
	}

	public function needsReadAccess() {
		return false;
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'sessionId' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'query' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_DEFAULT => ''
			],
			'followUpType' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => ''
			],
		];
	}
}
