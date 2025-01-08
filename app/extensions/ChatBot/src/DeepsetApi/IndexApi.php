<?php

namespace ChatBot\DeepsetApi;

use Config;
use Exception;
use MediaWiki\Http\HttpRequestFactory;
use Status;

class IndexApi extends Connector {
	/** @var ListApi */
	private ListApi $listApi;

	/**
	 * @param Config $config
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param ListApi $listApi
	 */
	public function __construct( Config $config, HttpRequestFactory $httpRequestFactory, ListApi $listApi ) {
		parent::__construct( $config, $httpRequestFactory );
		$this->listApi = $listApi;
	}

	/**
	 * @param string $indexName
	 * @param string $contents
	 * @param array $meta
	 *
	 * @return Status
	 * @throws Exception
	 */
	public function pushPage( string $indexName, string $contents, array $meta ): Status {
		$queryParams = sprintf( '?file_name=%s&write_mode=OVERWRITE', $indexName );
		$url = $this->indexUrl . $queryParams;

		$options = [
			'multipart' => [
				[
					'name' => 'text',
					'contents' => $contents
				],
				[
					'name' => 'meta',
					'contents' => json_encode( $meta, JSON_UNESCAPED_UNICODE )
				]
			]
		];

		try {
			$this->post( $url, $options, 'multipart/form-data; boundary=' );
		} catch ( Exception $e ) {
			return Status::newFatal( $e->getMessage() );
		}

		return Status::newGood();
	}

	/**
	 * @param string $filePath
	 * @param array $meta
	 *
	 * @return Status
	 */
	public function pushFile( string $filePath, array $meta ): Status {
		$url = $this->indexUrl . '?write_mode=OVERWRITE';

		$options = [
			'multipart' => [
				[
					'name' => 'file',
					'contents' => fopen( $filePath, 'r' ),
				],
				[
					'name' => 'meta',
					'contents' => json_encode( $meta, JSON_UNESCAPED_UNICODE )
				]
			]
		];

		try {
			$this->post( $url, $options, 'multipart/form-data; boundary=' );
		} catch ( Exception $e ) {
			return Status::newFatal( $e->getMessage() );
		}

		return Status::newGood();
	}

	/**
	 * @param array $toDelete // prefixed_title
	 *
	 * @return Status
	 * @throws Exception
	 */
	public function batchDelete( array $toDelete ): Status {
		// Make sure array is not empty otherwise ALL pages are going to be deleted in Deepset
		if ( empty( $toDelete ) ) {
			return Status::newGood();
		}

		try {
			$names = $this->listApi->getFiles( $toDelete );
		} catch ( Exception $e ) {
			return Status::newFatal( $e->getMessage() );
		}

		if ( empty( $names ) ) {
			return Status::newGood();
		}

		$body = [
			'names' => $names
		];
		$options = [ 'body' => json_encode( $body ) ];

		try {
			$this->delete( $this->indexUrl, $options );
		} catch ( Exception $e ) {
			return Status::newFatal( $e->getMessage() );
		}

		return Status::newGood();
	}

	/**
	 * Deletes every page and file from deepset index
	 *
	 * @return Status
	 * @throws Exception
	 */
	public function purgeIndex(): Status {
		$body = [
			'names' => []
		];
		$options = [ 'body' => json_encode( $body ) ];

		try {
			$this->delete( $this->indexUrl, $options );
		} catch ( Exception $e ) {
			return Status::newFatal( $e->getMessage() );
		}

		return Status::newGood();
	}
}
