<?php

namespace ChatBot\Rest;

use ChatBot\DeepsetApi\SessionApi;
use Exception;
use MediaWiki\Rest\SimpleHandler;

class Session extends SimpleHandler {
	/** @var SessionApi */
	private SessionApi $sessionApi;

	/**
	 * @param SessionApi $sessionApi
	 */
	public function __construct( SessionApi $sessionApi ) {
		$this->sessionApi = $sessionApi;
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function execute(): array {
		try {
			return $this->sessionApi->request();
		} catch ( Exception $e ) {
			return [
				'errors' => [ $e->getMessage() ],
			];
		}
	}

	public function needsReadAccess() {
		return false;
	}
}
