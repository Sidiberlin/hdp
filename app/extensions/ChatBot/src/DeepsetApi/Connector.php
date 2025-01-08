<?php

namespace ChatBot\DeepsetApi;

use Config;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use MediaWiki\Http\HttpRequestFactory;
use Psr\Http\Message\ResponseInterface;

class Connector {

	/** @var string */
	protected string $apiKey;

	/** @var string */
	protected string $sessionApiUrl;

	/** @var string */
	protected string $apiUrl;

	/** @var string */
	protected $indexUrl;

	/** @var string */
	protected $feedbackUrl;

	/** @var string */
	protected $tagUrl;

	/** @var string */
	protected $pipelineStatsUrl;

	/** @var HttpRequestFactory */
	protected HttpRequestFactory $requestFactory;

	/**
	 * @param Config $config
	 * @param HttpRequestFactory $httpRequestFactory
	 */
	public function __construct( Config $config, HttpRequestFactory $httpRequestFactory ) {
		$this->apiKey = $config->get( 'BmbfDeepsetApiKey' );
		$this->sessionApiUrl = $config->get( 'BmbfDeepsetApiSearchSessionsUrl' );
		$this->apiUrl = $config->get( 'BmbfDeepsetApiChatUrl' );
		$this->indexUrl = $config->get( 'BmbfDeepsetApiIndexUrl' );
		$this->feedbackUrl = $config->get( 'BmbfDeepsetApiFeedbackUrl' );
		$this->tagUrl = $config->get( 'BmbfDeepsetApiTagUrl' );
		$this->pipelineStatsUrl = $config->get( 'BmbfDeepsetPipelineStatsUrl' );
		$this->requestFactory = $httpRequestFactory;
	}

	/**
	 * @param string $url
	 * @param string $contentType
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function get(
		string $url,
		string $contentType = "application/json"
	): array {
		$response = $this->executeRequest( $url, [], 'GET', $contentType );
		$result = json_decode( $response->getBody()->getContents(), true );

		if ( !$result ) {
			return [];
		}

		return $result;
	}

	/**
	 * @param string $url
	 * @param array $options
	 * @param string $contentType
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function post( string $url, array $options, string $contentType = "application/json" ): array {
		$response = $this->executeRequest( $url, $options, 'POST', $contentType );
		$result = json_decode( $response->getBody()->getContents(), true );

		if ( !$result ) {
			return [];
		}

		return $result;
	}

	/**
	 * @param string $url
	 * @param array $options
	 *
	 * @return ResponseInterface
	 * @throws Exception
	 */
	protected function stream( string $url, array $options ): ResponseInterface {
		return $this->executeRequest( $url, $options, 'POST' );
	}

	/**
	 * @param string $url
	 * @param array $options
	 * @param string $contentType
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function patch( string $url, array $options, string $contentType = "application/json" ): array {
		$response = $this->executeRequest( $url, $options, 'PATCH', $contentType );
		$result = json_decode( $response->getBody()->getContents(), true );

		if ( !$result ) {
			return [];
		}

		return $result;
	}

	/**
	 * @param string $url
	 * @param array $options
	 * @param string $contentType
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function delete( string $url, array $options, string $contentType = "application/json" ): array {
		$response = $this->executeRequest( $url, $options, 'DELETE', $contentType );
		$result = json_decode( $response->getBody()->getContents(), true );

		if ( !$result ) {
			return [];
		}

		return $result;
	}

	/**
	 * @param string $url
	 * @param array $options
	 * @param string $method
	 * @param string $contentType
	 *
	 * @return ResponseInterface
	 * @throws Exception
	 */
	private function executeRequest(
		string $url,
		array $options = [],
		string $method = 'GET',
		string $contentType = "application/json"
	): ResponseInterface {
		$config['headers']['Authorization'] = 'Bearer ' . $this->apiKey;
		$config['headers']['User-Agent'] = $this->requestFactory->getUserAgent();
		$config['headers']['Content-Type'] = $contentType;
		$config['timeout'] = 120;

		$client = new Client( $config );

		try {
			return $client->request( $method, $url, $options );
		} catch ( GuzzleException $e ) {
			error_log( $e->getMessage() );
			throw new Exception( $e->getMessage() );
		}
	}
}
