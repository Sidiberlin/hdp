<?php

namespace MediaWiki\Extension\CollabPads\HookHandler;

use BlueSpice\Discovery\Hook\LastEditInfoHook;
use ChangeTags;
use MediaWiki\Revision\RevisionRecord;
use Message;
use Wikimedia\Rdbms\LoadBalancer;

class LastEditInfo implements LastEditInfoHook {

	/** @var LoadBalancer */
	private $loadBalancer;

	/**
	 * @param LoadBalancer $loadBalancer
	 */
	public function __construct( LoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @inheritDoc
	 */
	public function onLastEditInfo(
		RevisionRecord $revision, string $revisionDiffLink, string $lastEditorLink, Message &$lastEditInfo
	): void {
		if ( $this->isLastRevisionCollab( $revision ) ) {
			$lastEditInfo = Message::newFromKey(
				'collabpads-last-edit-info',
				$revisionDiffLink,
				$lastEditorLink
			);
		}
	}

	/**
	 * @param RevisionRecord $revision
	 * @return bool
	 */
	private function isLastRevisionCollab( RevisionRecord $revision ): bool {
		$tags = ChangeTags::getTags(
			$this->loadBalancer->getConnection( DB_REPLICA ),
			null,
			$revision->getId(),
			null
		);

		foreach ( $tags as $tag ) {
			if ( str_contains( $tag, 'collab-edit' ) ) {
				return true;
			}
		}

		return false;
	}
}
