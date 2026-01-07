<?php

namespace MediaWiki\Extension\CollabPads\Api\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CollabPads\CollabSessionManager;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use Wikimedia\ParamValidator\ParamValidator;

class GetSessionsHandler extends CollabSessionHandlerBase {

	/** @var CollabSessionManager */
	protected $collabSessionManager = null;

	/** @var PermissionManager */
	private $permissionManager;

	/**
	 * @param CollabSessionManager $collabSessionManager
	 * @param PermissionManager $permissionManager
	 */
	public function __construct( CollabSessionManager $collabSessionManager, PermissionManager $permissionManager ) {
		$this->collabSessionManager = $collabSessionManager;
		$this->permissionManager = $permissionManager;
	}

	public function run() {
		$owner = $this->getOwnerParam();
		if ( $owner === '*' ) {
			$this->assertPermissions( 'collabpadsessions-admin', $owner );
		}
		$allSessions = $this->collabSessionManager->getAllSessions( $owner );

		$output = [
			'count' => count( $allSessions ),
			'sessions' => $allSessions
		];

		return $this->getResponseFactory()->createJson( $output );
	}

	/**
	 * @return string
	 */
	private function getOwnerParam(): string {
		return $this->getValidatedParams()['owner'];
	}

	/**
	 * @param string $action
	 * @param string $owner
	 * @throws HttpException
	 */
	private function assertPermissions( string $action, string $owner ) {
		$user = RequestContext::getMain()->getUser();
		if ( !$this->permissionManager->userHasRight( $user, $action ) ) {
			throw new HttpException( 'Permission denied', 403 );
		}

		if (
			!$this->permissionManager->userHasRight( $user, 'collabpadsessions-admin' ) &&
			$owner !== $user->getName()
		) {
			throw new HttpException( 'Permission denied', 403 );
		}
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'owner' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_DEFAULT => '*'
			]
		];
	}
}
