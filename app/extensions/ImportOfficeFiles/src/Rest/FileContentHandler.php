<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Rest;

use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Extension\ImportOfficeFiles\Process\ImportResultReader;
use MediaWiki\Extension\ImportOfficeFiles\Workspace;
use MediaWiki\Rest\Handler;
use Wikimedia\ParamValidator\ParamValidator;

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
		$config = json_decode( $this->decodePath( $params ), true );
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

	/**
	 * @param string $path
	 * @return string
	 */
	private function decodePath( string $path ): string {
		$path = rawurldecode( $path );

		if (
			!mb_check_encoding( $path, 'UTF-8' ) &&
			mb_check_encoding( $path, 'ISO-8859-1' )
		) {
			$path = mb_convert_encoding( $path, 'UTF-8', 'ISO-8859-1' );
		}

		return $path;
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
