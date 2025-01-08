<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Rest;

use Exception;
use MediaWiki\Extension\ImportOfficeFiles\Process\FileConvertProcessStep;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\SimpleHandler;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use RequestContext;
use Wikimedia\ParamValidator\ParamValidator;
use function Sabre\HTTP\decodePath;

class FileAnalyzeHandler extends SimpleHandler {

	public function __construct() {
	}

	public function run() {
		$request = $this->getRequest();
		$uploadId = $request->getPathParam( "uploadId" );
		$file = $request->getPathParam( "filename" );

		$params = $request->getUri()->getQuery();
		$config = json_decode( decodePath( $params ), true );
		$title = $config['config']['title'];
		$structure = $config['config']['structure'];
		$conflict = $config['config']['conflict'];

		$context = RequestContext::getMain();

		$process = new ManagedProcess( [
			'file-convert-step' => [
				'class' => FileConvertProcessStep::class,
				'args' => [
					$uploadId,
					$file,
					$title,
					$structure,
					$conflict,
					$context->getUser()->getName()
				]
			]
		], 300 );

		/** @var \MWStake\MediaWiki\Component\ProcessManager\ProcessManager $processManager */
		$processManager = MediaWikiServices::getInstance()->getService( 'ProcessManager' );

		try {
			$processId = $processManager->startProcess( $process );
		} catch ( Exception $e ) {
			return $this->getResponseFactory()->createJson( [
				'success' => false,
				'error' => $e->getMessage()
			] );
		}

		return $this->getResponseFactory()->createJson( [
			'success' => true,
			'processId' => $processId
		] );
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'uploadId' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			],
			'filename' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			]
		];
	}

}
