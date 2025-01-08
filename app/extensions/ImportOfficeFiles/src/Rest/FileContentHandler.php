<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Rest;

use Config;
use ConfigFactory;
use MediaWiki\Extension\ImportOfficeFiles\Process\ImportResultReader;
use MediaWiki\Extension\ImportOfficeFiles\Workspace;
use MediaWiki\Rest\Handler;
use Wikimedia\ParamValidator\ParamValidator;
use function Sabre\HTTP\decodePath;

class FileContentHandler extends Handler {

	/**
	 * @var string
	 */
	private $uploadDirectory;

	/**
	 * @var Workspace
	 */
	private $workspace;

	/**
	 * @param Config $mainConfig
	 * @param ConfigFactory $configFactory
	 */
	public function __construct( Config $mainConfig, ConfigFactory $configFactory ) {
		$this->uploadDirectory = $mainConfig->get( 'UploadDirectory' );

		$config = $configFactory->makeConfig( 'main' );
		$this->workspace = new Workspace( $config );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$request = $this->getRequest();
		$uploadId = $request->getPathParam( "uploadId" );

		$params = $request->getUri()->getQuery();
		$config = json_decode( decodePath( $params ), true );
		$title = $config['title'];

		$workspaceDir = $this->uploadDirectory . '/cache/ImportOfficeFiles';

		$this->workspace->init( $uploadId, $workspaceDir );

		// Read out "result.xml" and create array with pages titles and pages content
		$xmlReader = new ImportResultReader( $this->workspace );
		$res = $xmlReader->readResult();

		$output = [
			'content' => $res[$title]['content']
		];

		return $this->getResponseFactory()->createJson( $output );
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'uploadId' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			]
		];
	}
}
