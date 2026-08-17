<?php

namespace ChatBot\Rest;

use ChatBot\DeepsetApi\ChatApi;
use Exception;
use MediaWiki\Rest\HttpException;
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
	 * @throws HttpException
	 */
	public function execute(): array {
		$params = $this->getValidatedParams();
		$query = $params['query'];
		$sessionId = $params['sessionId'];
		$followUpType = $params['followUpType'];

		// Both guards must sit before the try/catch: its catch (Exception)
		// would swallow the HttpException into a 200 error frame, and
		// chatApi->request() commits SSE headers that cannot be taken back.
		$authority = $this->getAuthority();
		if ( !$authority->isRegistered() ) {
			throw new HttpException( 'rest-read-denied', 403 );
		}
		if ( !$authority->isAllowed( 'read' ) ) {
			throw new HttpException( 'rest-read-denied', 403 );
		}

		try {
			$this->chatApi->request( $query, $sessionId, $followUpType );

			return [];
		} catch ( Exception $e ) {
			return [
				'errors' => [ $e->getMessage() ],
			];
		}
	}

	/**
	 * @return string[]
	 */
	public function getSupportedRequestTypes(): array {
		return [ 'text/event-stream' ];
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
