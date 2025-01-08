<?php

namespace MediaWiki\Extension\CollabPads\Api\Rest;

use Wikimedia\ParamValidator\ParamValidator;

class DeleteSessionHandler extends CollabSessionHandlerBase {

	public function run() {
		$request = $this->getRequest();
		$pageNamespace = $request->getPathParam( "pageNamespace" );
		$pageTitle = $request->getPathParam( "pageTitle" );
		$pageTitle = $this->unmaskPageTitle( $pageTitle );

		$this->collabSessionManager->deleteSession( $pageNamespace, $pageTitle );

		$output = [
			'success' => true,
			'processName' => "session delete",
		];

		return $this->getResponseFactory()->createJson( $output );
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'pageNamespace' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			],
			'pageTitle' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			]
		];
	}

}
