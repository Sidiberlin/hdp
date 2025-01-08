<?php

namespace MediaWiki\Extension\CollabPads\Api\Rest;

use MediaWiki\Extension\CollabPads\CollabPadAccessTokenDAO;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserFactory;
use Psr\Log\LoggerInterface;
use Title;
use User;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\ILoadBalancer;

class AccessUserHandler extends SimpleHandler {

	/** @var LoggerInterface */
	private $logger;

	/** @var PermissionManager */
	private $permissionManager;

	/**
	 * @var UserFactory
	 */
	private $userFactory;

	/**
	 * @var CollabPadAccessTokenDAO
	 */
	private $accessTokenDAO;

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'pageTitle' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			],
			'token' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			]
		];
	}

	/**
	 * @param PermissionManager $permissionManager
	 * @param UserFactory $userFactory
	 * @param ILoadBalancer $lb
	 */
	public function __construct( PermissionManager $permissionManager, UserFactory $userFactory, ILoadBalancer $lb ) {
		$this->permissionManager = $permissionManager;
		$this->userFactory = $userFactory;
		$this->accessTokenDAO = new CollabPadAccessTokenDAO( $lb );

		$this->logger = LoggerFactory::getInstance( 'CollabPads' );
	}

	/**
	 * @return \MediaWiki\Rest\Response
	 */
	public function run(): Response {
		$this->logger->info( "Start checking Access rights!" );

		$request = $this->getRequest();

		$pageTitle = $this->parseTitle( $request->getPathParam( 'pageTitle' ) );

		$token = $request->getPathParam( 'token' );

		$userId = $this->accessTokenDAO->recognizeUser( $token, $pageTitle->getPrefixedDBkey() );
		$user = $this->userFactory->newFromId( $userId );

		$this->logger->debug( "User ID - $userId" );

		if (
			$this->permissionManager->userCan( 'read', $user, $pageTitle )
			&& $this->permissionManager->userCan( 'edit', $user, $pageTitle )
		) {
			// Drop "access token" after giving access, they are just for one-time usage
			$this->accessTokenDAO->dropToken( $userId, $pageTitle->getPrefixedDBkey() );

			$this->logger->info( "Access to page {$pageTitle} for {$user} is granted!" );
			return $this->grantedAccessResponse( $user, $pageTitle );
		}
		$this->logger->info( "Access to page {$pageTitle} for {$user} is denied!" );
		return $this->deniedAccessResponse( $user );
	}

	/**
	 * @param string $titleFromRequest
	 * @return Title
	 */
	private function parseTitle( string $titleFromRequest ): Title {
		$pageName = $titleFromRequest;

		$pageName = str_replace( "|", "/", $pageName );

		return Title::newFromText( $pageName );
	}

	/**
	 * @param User $user
	 * @param Title $pageTitle
	 * @return Response
	 */
	private function grantedAccessResponse( User $user, Title $pageTitle ): Response {
		$userData['userName'] = $user->getName();

		return $this->getResponseFactory()->createJson( [
			'access' => true,
			'user' => $userData,
			'pageTitle' => $pageTitle->mUrlform,
			'pageNamespace' => $pageTitle->getNamespace(),
			'message' => 'Access granted!',
			'error' => null
		] );
	}

	/**
	 * @param User $user
	 * @return Response
	 */
	private function deniedAccessResponse( User $user ): Response {
		return $this->getResponseFactory()->createJson( [
			'access' => false,
			'user' => $user,
			'message' => 'Access denied!',
			'error' => 'User need permission to read or/and edit the page'
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function needsReadAccess() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function needsWriteAccess() {
		return false;
	}
}
