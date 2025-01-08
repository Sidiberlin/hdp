<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Rest;

use Config;
use ConfigFactory;
use MediaWiki\Extension\ImportOfficeFiles\Process\ImportResultReader;
use MediaWiki\Extension\ImportOfficeFiles\Workspace;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\Response;
use Wikimedia\ParamValidator\ParamValidator;

class FileStructureHandler extends Handler {

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
	 * @return Response
	 */
	public function execute() {
		$request = $this->getRequest();
		$uploadId = $request->getPathParam( "uploadId" );

		$workspaceDir = $this->uploadDirectory . '/cache/ImportOfficeFiles';

		$this->workspace->init( $uploadId, $workspaceDir );

		// Read out "result.xml" and create array with pages titles and pages content
		$xmlReader = new ImportResultReader( $this->workspace );
		$res = [
			'pages' => $xmlReader->readResult()
		];

		return $this->getResponseFactory()->createJson( $res );
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
