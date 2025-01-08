<?php

namespace SimpleTasks\Rest;

use DateTime;
use Language;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\Handler;
use MediaWiki\User\UserFactory;
use MWException;
use RawMessage;
use RequestContext;
use SimpleTasks\SimpleTaskManager;
use Title;
use TitleFactory;
use User;
use Wikimedia\ParamValidator\ParamValidator;

class RetrieveTasksFromFilter extends Handler {

	/** @var SimpleTaskManager */
	private $taskManager;

	/** @var UserFactory */
	private $userFactory;

	/** @var Language */
	private $language;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var User */
	private $currentUser = null;

	/** @var Language */
	private $userlanguage = null;

	/**
	 * @param SimpleTaskManager $taskManager
	 * @param UserFactory $userFactory
	 * @param Language $language
	 * @param TitleFactory $titleFactory
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		SimpleTaskManager $taskManager,
		UserFactory $userFactory,
		Language $language,
		TitleFactory $titleFactory,
		PermissionManager $permissionManager
	) {
		$this->taskManager = $taskManager;
		$this->userFactory = $userFactory;
		$this->language = $language;
		$this->titleFactory = $titleFactory;
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$validated = $this->getValidatedParams();
		$count = $validated['count'];
		$user = $this->getUser();
		$states = $this->getStates();
		$date = $this->getDate();
		$namespaces = $this->getNamespaces();
		$context = RequestContext::getMain();
		$this->currentUser = $context->getUser();
		$this->userlanguage = $context->getLanguage();

		$rawTasks = $this->getTasksFromFilter( $user, $states, $date, $namespaces );
		$tasks = $this->getTasks( $rawTasks );
		return $this->getResponseFactory()->createJson( [
			'count' => $count,
			'tasks' => $tasks,
			'success' => true
		] );
	}

	/**
	 *
	 * @param array $rawTasks
	 * @return array
	 */
	private function getTasks( $rawTasks ) {
		$tasks = [];
		foreach ( $rawTasks as $rawTask ) {
			$pageId = $rawTask->getChecklistItem()->getPage()->getId();
			$title = $this->titleFactory->newFromID( $pageId );

			$userCanRead = $this->checkReadPermissionForTitle( $title );
			if ( !$userCanRead ) {
				continue;
			}
			$task = $rawTask->jsonSerialize();
			$text = new RawMessage( $task['text'] );
			$task['text'] = $text->parse();
			$task['page_title'] = $title->getFullText();
			$task['page_url' ] = $title->getLocalURL();
			$task['completed'] = $task[ 'completed' ] ? 'done' : 'open';
			$task = $this->setUserData( $task );
			$task = $this->setDateData( $task );

			$tasks[] = $task;
		}
		return $tasks;
	}

	/**
	 *
	 * @param Title $title
	 * @return bool
	 */
	private function checkReadPermissionForTitle( $title ) {
		$userCanRead = $this->permissionManager->userCan(
			'read',
			$this->userFactory->newFromUserIdentity( $this->currentUser ),
			$title
		);
		return $userCanRead;
	}

	/**
	 *
	 * @param array $task
	 * @return array
	 */
	private function setDateData( $task ) {
		$date = $task['dueDate'];
		$dateFormatted = $this->userlanguage->userDate( $date, $this->currentUser );
		$task['dueDate'] = $dateFormatted;
		return $task;
	}

	/**
	 *
	 * @param array $task
	 * @return array
	 */
	private function setUserData( $task ) {
		$userName = $task['assignee'];
		$user = $this->userFactory->newFromName( $userName );
		$task['assignee'] = $user->getName();
		return $task;
	}

	/**
	 * @param array $user
	 * @param array $states
	 * @param DateTime|null $date
	 * @param array $namespaces
	 * @return array
	 */
	private function getTasksFromFilter( $user, $states, $date, $namespaces ) {
		foreach ( $user as $userId ) {
			$this->taskManager->forUser( $userId );
		}
		foreach ( $states as $state ) {
			$this->taskManager->completed( $state );
		}
		if ( $date instanceof DateTime ) {
			$this->taskManager->forDate( $date, 'lt' );
		}
		foreach ( $namespaces as $namespace ) {
			$this->taskManager->forNamespace( $namespace );
		}
		$tasks = $this->taskManager->query();
		return $tasks;
	}

	/**
	 * @return DateTime|null
	 */
	private function getDate() {
		$validated = $this->getValidatedParams();

		if ( !is_array( $validated ) || !isset( $validated['date'] ) ) {
			return null;
		}
		$ts = json_decode( $validated['date'], 1 );
		$ts = trim( $ts );
		$time = strtotime( $ts );
		if ( $time === false ) {
			return null;
		}
		$date = new DateTime();
		$date->setTimestamp( $time );
		return $date;
	}

	/**
	 * @return array
	 */
	private function getUser(): array {
		$validated = $this->getValidatedParams();

		if ( !is_array( $validated ) || !isset( $validated['user'] ) ) {
			return [];
		}
		$user = [];
		$userParam = json_decode( $validated['user'], 1 );

		$userNames = explode( '|', $userParam );
		foreach ( $userNames as $userName ) {
			$userFromName = $this->userFactory->newFromName( $userName );
			if ( !$userFromName ) {
				continue;
			}
			$user[] = $userFromName;
		}

		return $user;
	}

	/**
	 * @return array
	 */
	private function getStates(): array {
		$validated = $this->getValidatedParams();

		if ( !is_array( $validated ) || !isset( $validated['state'] ) ) {
			return [];
		}
		$states = [];
		$statesParam = json_decode( $validated['state'], 1 );

		$givenStates = explode( '|', $statesParam );
		foreach ( $givenStates as $state ) {
			if ( $state === 'checked' ) {
				$states[] = true;
				continue;
			}
			if ( $state === 'unchecked' ) {
				$states[] = false;
			}
		}

		return $states;
	}

	/**
	 *
	 * @return array
	 */
	private function getNamespaces() {
		$validated = $this->getValidatedParams();
		if ( !is_array( $validated ) || !isset( $validated['namespace'] ) ) {
			return [];
		}

		$csv = json_decode( $validated['namespace'] );
		if ( !isset( $csv ) || !is_string( $csv ) ) {
			throw new MWException(
				__CLASS__ . ":" . __METHOD__ . ' - expects comma separated string'
			);
		}

		$csv = trim( $csv );
		// make namespaces case insensitive
		$csv = mb_strtolower( $csv );
		if ( in_array( $csv, [ 'all', '-', '' ] ) ) {
			return array_keys( $this->language->getNamespaces() );
		}
		$ambigousNS = explode( '|', $csv );
		$ambigousNS = array_map( 'trim', $ambigousNS );
		$validNSIndexes = [];
		$invalidNS = [];

		foreach ( $ambigousNS as $ambigousNamespace ) {
			if ( is_numeric( $ambigousNamespace ) ) {
				// Given value is a namespace id.
				if ( $this->language->getNsText( $ambigousNamespace ) === false ) {
					// Does a namespace with the given id exist?
					$invalidNS[] = $ambigousNamespace;
				} else {
					$validNSIndexes[] = $ambigousNamespace;
				}
			} else {
				if ( $ambigousNamespace == wfMessage( 'bs-ns_main' )->plain()
					|| strcmp( $ambigousNamespace, "main" ) === 0 ) {
					$namespaceIdFromText = 0;
				} elseif ( $ambigousNamespace == '' ) {
					$namespaceIdFromText = 0;
				} else {
					// Given value is a namespace text.
					// 'Bluespice talk' -> 'Bluespice_talk'
					$ambigousNamespace = str_replace( ' ', '_', $ambigousNamespace );
					// Does a namespace id for the given namespace text exist?
					$namespaceIdFromText = $this->language->getNsIndex( $ambigousNamespace );
				}
				if ( $namespaceIdFromText === false ) {
					$invalidNS[] = $ambigousNamespace;
				} else {
					$validNSIndexes[] = $namespaceIdFromText;
				}
			}
		}

		// Does the given CSV list contain any invalid namespaces?
		if ( !empty( $invalidNS ) ) {
			// TODO: fix error description
			foreach ( $invalidNS as $namespace ) {

			}
			throw new MWException( 'Invalid namespaces: ' . $invalidNS );
		}

		// minify the Array, rearrange indexes and return it
		return array_values( array_unique( $validNSIndexes ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		return [
			'count' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_DEFAULT => '0',
			],
			'user' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_REQUIRED => false
			],
			'date' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_REQUIRED => false
			],
			'state' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_REQUIRED => false
			],
			'namespace' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_REQUIRED => false
			]
		];
	}

}
