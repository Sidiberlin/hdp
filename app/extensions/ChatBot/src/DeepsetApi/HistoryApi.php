<?php

namespace ChatBot\DeepsetApi;

use Exception;

class HistoryApi extends Connector {
	private const FETCH_LIMIT = 50;

	/**
	 * @param string $sessionId
	 * @param string $after
	 *
	 * @return array
	 */
	public function request( string $sessionId, string $after ): array {
		$params = [
			'filter' => "session_id eq " . $sessionId,
			'limit' => self::FETCH_LIMIT,
			'after' => $after,
		];

		$queryString = http_build_query( $params );

		try {
			return $this->get(
				"$this->apiUrl/search_history?$queryString",
			);
		} catch ( Exception $e ) {
			return [
				'errors' => [ $e->getMessage() ],
			];
		}
	}
}
