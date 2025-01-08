<?php

namespace ChatBot\DeepsetApi;

use Exception;

class SessionApi extends Connector {
	/**
	 * @return array
	 * @throws Exception
	 */
	public function request(): array {
		$response = $this->get( $this->apiUrl );

		if ( !$response['pipeline_id'] ) {
			return [
				'errors' => [ 'Missing pipeline id' ],
			];
		}

		$options = [
			'body' => json_encode( [ "pipeline_id" => $response['pipeline_id'] ] ),
		];

		try {
			return $this->post(
				$this->sessionApiUrl,
				$options
			);
		} catch ( Exception $e ) {
			return [
				'errors' => [ $e->getMessage() ],
			];
		}
	}
}
