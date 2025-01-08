<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Rest;

use Config;
use Exception;
use MediaWiki\Extension\ImportOfficeFiles\Process\ImportProcess\ImportImagesStep;
use MediaWiki\Extension\ImportOfficeFiles\Process\ImportProcess\ImportPagesStep;
use MediaWiki\Extension\ImportOfficeFiles\Process\ImportProcess\RemoveFilesStep;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\SimpleHandler;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use RequestContext;
use Wikimedia\ParamValidator\ParamValidator;

class FileImportHandler extends SimpleHandler {

	/**
	 * @var string
	 */
	private $uploadDirectory;

	/**
	 * @param Config $mainConfig
	 */
	public function __construct( Config $mainConfig ) {
		$this->uploadDirectory = $mainConfig->get( 'UploadDirectory' );
	}

	/**
	 * @return \MediaWiki\Rest\Response
	 */
	public function run() {
		$request = $this->getRequest();

		$uploadId = $request->getPathParam( "uploadId" );

		$context = RequestContext::getMain();
		$user = $context->getUser();
		$username = '';
		if ( !$user->isAnon() ) {
			$username = $user->getName();
		}

		$importXmlScript = $GLOBALS['IP'] . '/maintenance/importDump.php';
		$importImagesScript = $GLOBALS['IP'] . '/maintenance/importImages.php';

		$process = new ManagedProcess( [
			'import-images-step' => [
				'class' => ImportImagesStep::class,
				'args' => [
					$uploadId,
					$this->uploadDirectory,
					$importImagesScript,
					$username
				]
			],
			'import-pages-step' => [
				'class' => ImportPagesStep::class,
				'args' => [
					$uploadId,
					$this->uploadDirectory,
					$importXmlScript
				]
			],
			'remove-files-step' => [
				'class' => RemoveFilesStep::class,
				'args' => [
					$uploadId,
					$this->uploadDirectory
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
