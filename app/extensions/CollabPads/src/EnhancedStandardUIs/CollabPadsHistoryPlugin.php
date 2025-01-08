<?php

namespace MediaWiki\Extension\CollabPads\EnhancedStandardUIs;

use MediaWiki\Extension\CollabPads\CollabRevisionManager;
use MediaWiki\Extension\EnhancedStandardUIs\IHistoryPlugin;
use Wikimedia\Rdbms\ILoadBalancer;

class CollabPadsHistoryPlugin implements IHistoryPlugin {

	/** @var ILoadBalancer */
	private $loadBalancer;

	/**
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules( $historyAction ): array {
		return [ 'ext.collabpads.enhanced.history' ];
	}

	/**
	 * @inheritDoc
	 */
	public function ammendRow( $historyAction, &$entry, &$attribs, &$classes ) {
		if ( !str_contains( $entry['tags'], 'collab-edit' ) ) {
			return;
		}

		$collabRevisionManager = new CollabRevisionManager( $this->loadBalancer );
		$collabParticipants = $collabRevisionManager->getParticipants( $entry['id'] );

		$entry['author'] = $collabParticipants;
	}
}
