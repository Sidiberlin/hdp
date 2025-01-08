<?php

namespace MediaWiki\Extension\CollabPads\HookHandler;

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
use MediaWiki\Extension\CollabPads\CollabRevisionManager;
use MediaWiki\Extension\CollabPads\CollabSessionManager;
use MediaWiki\Hook\RecentChange_saveHook;

class RecentChangeSaveHandler implements RecentChange_saveHook {

	/** @var CollabSessionManager */
	private $collabSessionManager;

	/** @var CollabRevisionManager */
	private $collabRevisionManager;

	/**
	 * @param CollabSessionManager $collabSessionManager
	 * @param CollabRevisionManager $collabRevisionManager
	 */
	public function __construct(
		CollabSessionManager $collabSessionManager,
		CollabRevisionManager $collabRevisionManager
	) {
		$this->collabSessionManager = $collabSessionManager;
		$this->collabRevisionManager = $collabRevisionManager;
	}

	/**
	 * @inheritDoc
	 */
	public function onRecentChange_save( $recentChange ) {
		$namespace = $recentChange->getAttribute( 'rc_namespace' );
		$title = $recentChange->getAttribute( 'rc_title' );
		$session = $this->collabSessionManager->getSession( $namespace, $title );

		if ( !empty( $session ) ) {
			$recentChange->addTags( [ 'collab-edit' ] );
		}
	}
}
