<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Rest;

use Exception;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class ImportProceedHandler extends SimpleHandler {

	/**
	 * @return \MediaWiki\Rest\Response
	 */
	public function run() {
		$request = $this->getRequest();
		$processId = $request->getPathParam( 'processId' );

		/** @var \MWStake\MediaWiki\Component\ProcessManager\ProcessManager $processManager */
		$processManager = MediaWikiServices::getInstance()->getService( 'ProcessManager' );

		$error = null;
		try {
			$processId = $processManager->proceed( $processId );
		} catch ( Exception $e ) {
			$error = $e->getMessage();
		}

		$output = [
			'success' => true,
			'processId' => $processId
		];

		if ( $error ) {
			$output = [
				'success' => false,
				'error' => $error
			];
		}

		return $this->getResponseFactory()->createJson( $output );
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'processId' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			]
		];
	}

}
