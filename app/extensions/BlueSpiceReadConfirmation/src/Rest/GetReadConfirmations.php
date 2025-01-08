<?php

namespace BlueSpice\ReadConfirmation\Rest;

use BlueSpice\PageAssignments\AssignmentFactory;
use BlueSpice\ReadConfirmation\MechanismFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserFactory;
use Message;
use RequestContext;
use TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class GetReadConfirmations extends SimpleHandler {

	/** @var MechanismFactory */
	private $mechanismFactory = null;

	/** @var AssignmentFactory */
	private $assignmentFactory = null;

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var UserFactory */
	private $userFactory = null;

	/** @var PermissionManager */
	private $permissionManager = null;

	/**
	 *
	 * @param MechanismFactory $mechanismFactory
	 * @param AssignmentFactory $assignmentFactory
	 * @param TitleFactory $titleFactory
	 * @param UserFactory $userFactory
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		MechanismFactory $mechanismFactory,
		AssignmentFactory $assignmentFactory,
		TitleFactory $titleFactory,
		UserFactory $userFactory,
		PermissionManager $permissionManager
	) {
		$this->mechanismFactory = $mechanismFactory;
		$this->assignmentFactory = $assignmentFactory;
		$this->titleFactory = $titleFactory;
		$this->userFactory = $userFactory;
		$this->permissionManager = $permissionManager;
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function run() {
		$request = $this->getRequest();
		$pageId = (int)$request->getPathParam( 'pageId' );

		$userIsAllowed = $this->checkViewPermissions();
		if ( !$userIsAllowed ) {
			throw new HttpException( Message::newFromKey(
				"bs-readconfirmation-api-confirmation-error-no-read-permission"
			) );
		}

		$title = $this->titleFactory->newFromID( $pageId );
		if ( !$title ) {
			throw new HttpException( Message::newFromKey(
				"bs-readconfirmation-api-confirmation-error-no-valid-title"
			) );
		}
		$mechanismInstance = $this->mechanismFactory->getMechanismInstance();
		if ( !$mechanismInstance->mustRead( $title ) ) {
			return $this->getResponseFactory()->createJson( [
				'success' => true,
				'results' => []
			] );
		}

		$assignmentTitle = $this->assignmentFactory->newFromTargetTitle( $title );
		if ( !$assignmentTitle ) {
			throw new HttpException( Message::newFromKey(
				"bs-readconfirmation-api-confirmation-error-no-assignment-title"
			) );
		}
		$assignedUsers = $assignmentTitle->getAssignedUserIDs();
		$readConfirmations = $mechanismInstance->getCurrentReadConfirmations( $assignedUsers, [ $pageId ] );
		$usersConfirmation = [];

		foreach ( $assignedUsers as $userId ) {
			$confirmation = false;
			if ( isset( $readConfirmations[ $pageId ][ $userId ] ) ) {
				$confirmation = true;
			}
			$user = $this->userFactory->newFromId( $userId );
			$username = !empty( $user->getRealName() ) ? $user->getRealName() : $user->getName();
			$usersConfirmation[] = [
				'user' => $username,
				'confirmation' => $confirmation
			];
		}

		return $this->getResponseFactory()->createJson( [
			'success' => true,
			'results' => $usersConfirmation
		] );
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'pageId' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			]
		];
	}

	/**
	 *
	 * @return bool
	 */
	private function checkViewPermissions() {
		$context = RequestContext::getMain();
		$user = $context->getUser();

		if ( $this->permissionManager->userHasRight( $user, 'readconfirmationviewconfirmations' ) ) {
			return true;
		}
		return false;
	}

}
