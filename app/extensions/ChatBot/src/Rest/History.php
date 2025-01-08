<?php

namespace ChatBot\Rest;

use ChatBot\DeepsetApi\HistoryApi;
use Exception;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use Wikimedia\ParamValidator\ParamValidator;

class History extends SimpleHandler {
	/** @var HistoryApi */
	private HistoryApi $historyApi;

	/**
	 * @param HistoryApi $historyApi
	 */
	public function __construct( HistoryApi $historyApi ) {
		$this->historyApi = $historyApi;
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function execute(): array {
		$body = $this->getValidatedBody();

		try {
			return $this->historyApi->request( $body['sessionId'], $body['after'] );
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
	 * @param string $contentType
	 *
	 * @return JsonBodyValidator
	 */
	public function getBodyValidator( $contentType ): JsonBodyValidator {
		return new JsonBodyValidator( [
			'sessionId' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'after' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_DEFAULT => -1
			],
		] );
	}
}
