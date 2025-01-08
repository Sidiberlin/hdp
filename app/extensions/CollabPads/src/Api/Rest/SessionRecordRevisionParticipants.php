<?php

namespace MediaWiki\Extension\CollabPads\Api\Rest;

use MediaWiki\Extension\CollabPads\CollabRevisionManager;
use MediaWiki\Extension\CollabPads\CollabSessionManager;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\User\ActorNormalization;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\ILoadBalancer;

class SessionRecordRevisionParticipants extends CollabSessionHandlerBase {

	/** @var CollabRevisionManager */
	private $collabRevisionManager = null;

	/** @var RevisionLookup */
	private $revisionLookup = null;

	/** @var ActorNormalization */
	private $actorNormalization = null;

	/** @var ILoadBalancer */
	private $loadBalancer = null;

	/**
	 * @param CollabSessionManager $collabSessionManager
	 * @param CollabRevisionManager $collabRevisionManager
	 * @param RevisionLookup $revisionLookup
	 * @param ActorNormalization $actorNormalization
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct(
		CollabSessionManager $collabSessionManager,
		CollabRevisionManager $collabRevisionManager,
		RevisionLookup $revisionLookup,
		ActorNormalization $actorNormalization,
		ILoadBalancer $loadBalancer
		) {
		parent::__construct( $collabSessionManager );
		$this->collabRevisionManager = $collabRevisionManager;
		$this->revisionLookup = $revisionLookup;
		$this->actorNormalization = $actorNormalization;
		$this->loadBalancer = $loadBalancer;
	}

	public function run() {
		$request = $this->getRequest();

		$pageNamespace = $request->getPathParam( "pageNamespace" );
		$pageTitle = $request->getPathParam( "pageTitle" );
		$pageTitle = $this->unmaskPageTitle( $pageTitle );
		$revisionId = $request->getPathParam( "revisionId" );

		$session = $this->collabSessionManager->getSession( $pageNamespace, $pageTitle );
		if ( empty( $session ) ) {
			return $this->getResponseFactory()->createJson( [
				'success' => false,
				'error' => 'No session found',
			] );
		}

		$revison = $this->revisionLookup->getRevisionById( $revisionId );
		if ( !$revison ) {
			return $this->getResponseFactory()->createJson( [
				'success' => false,
				'error' => 'Revision not found',
			] );
		}

		$actorId = $this->actorNormalization->acquireActorId(
			$revison->getUser(),
			$this->loadBalancer->getConnection( DB_PRIMARY )
		);

		$participantUsernames = json_decode( $request->getBody()->getContents(), JSON_UNESCAPED_SLASHES );

		try {
			// The participants of a revision can only be recorded once!
			// If there is already an entry in the DB we must not overwrite it.
			$this->collabRevisionManager->insertSession(
				$revisionId,
				$session['sessionId'],
				$pageNamespace,
				$pageTitle,
				$session['owner'],
				$actorId,
				serialize( $participantUsernames )
			);

			$output = [
				'success' => true,
				'processId' => $request,
			];
		} catch ( \Exception $e ) {
			$output = [
				'success' => false,
				'error' => $e->getMessage(),
			];
		}

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
			],
			'revisionId' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'integer'
			]
		];
	}
}
