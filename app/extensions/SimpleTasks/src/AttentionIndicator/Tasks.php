<?php

namespace SimpleTasks\AttentionIndicator;

use BlueSpice\Discovery\AttentionIndicator;
use Config;
use MediaWiki\MediaWikiServices;
use SimpleTasks\SimpleTaskManager;
use User;

class Tasks extends AttentionIndicator {

	/**
	 * @var SimpleTaskManager
	 */
	private $taskManager = null;

	/**
	 * @param string $key
	 * @param Config $config
	 * @param User $user
	 * @param SimpleTaskManager $taskManager
	 */
	public function __construct( string $key, Config $config, User $user,
		SimpleTaskManager $taskManager ) {
		$this->taskManager = $taskManager;
		parent::__construct( $key, $config, $user );
	}

	/**
	 * @param string $key
	 * @param Config $config
	 * @param User $user
	 * @param MediaWikiServices $services
	 * @param SimpleTaskManager|null $taskManager
	 * @return IAttentionIndicator
	 */
	public static function factory( string $key, Config $config, User $user,
		MediaWikiServices $services, SimpleTaskManager $taskManager = null ) {
		if ( !$taskManager ) {
			$taskManager = $services->get( 'SimpleTaskManager' );
		}
		return new static(
			$key,
			$config,
			$user,
			$taskManager
		);
	}

	/**
	 * @return int
	 */
	protected function doIndicationCount(): int {
		$count = 0;
		$tasks = $this->taskManager->forUser( $this->user )->completed( false )->query();

		foreach ( $tasks as $task ) {
			$count++;
		}
		return $count;
	}

}
