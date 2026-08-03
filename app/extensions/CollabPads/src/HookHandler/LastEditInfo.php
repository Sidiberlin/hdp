<?php

namespace MediaWiki\Extension\CollabPads\HookHandler;

use BlueSpice\Discovery\Hook\LastEditInfoHook;
use MediaWiki\ChangeTags\ChangeTagsStore;
use MediaWiki\Message\Message;
use MediaWiki\Revision\RevisionRecord;
use Wikimedia\Rdbms\LoadBalancer;

class LastEditInfo implements LastEditInfoHook {

	/** @var LoadBalancer */
	private $loadBalancer;

	/** @var ChangeTagsStore */
	private $changeTagsStore;

	/**
	 * @param LoadBalancer $loadBalancer
	 * @param ChangeTagsStore $changeTagsStore
	 */
	public function __construct( LoadBalancer $loadBalancer, ChangeTagsStore $changeTagsStore ) {
		$this->loadBalancer = $loadBalancer;
		$this->changeTagsStore = $changeTagsStore;
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
		$tags = $this->changeTagsStore->getTags(
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
