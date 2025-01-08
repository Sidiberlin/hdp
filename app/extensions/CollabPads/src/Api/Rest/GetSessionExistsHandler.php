<?php

namespace MediaWiki\Extension\CollabPads\Api\Rest;

use MediaWiki\Extension\CollabPads\CollabSessionManager;
use Wikimedia\ParamValidator\ParamValidator;

class GetSessionExistsHandler extends CollabSessionHandlerBase {

	/**
	 * Undocumented function
	 *
	 * @param CollabSessionManager $collabSessionManager
	 */
	public function __construct( CollabSessionManager $collabSessionManager ) {
		parent::__construct( $collabSessionManager );
	}

	public function run() {
		$request = $this->getRequest();

		$pageNamespace = $request->getPathParam( "pageNamespace" );
		$pageTitle = $request->getPathParam( "pageTitle" );
		$pageTitle = $this->unmaskPageTitle( $pageTitle );
		$sessionPermissions = $this->collabSessionManager->getSessionExistsByName( $pageNamespace, $pageTitle );
		if ( empty( $sessionPermissions ) ) {
			return $this->getResponseFactory()->createJson( [ 'session_exist' => false ] );
		}
		$output = [ 'session_exist' => true ];
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
