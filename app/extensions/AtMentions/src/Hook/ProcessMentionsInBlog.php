<?php

namespace AtMentions\Hook;

use AtMentions\Event\UserMentionInEntity;
use ManualLogEntry;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;

class ProcessMentionsInBlog extends ProcessMentions {
	/**
	 * @inheritDoc
	 */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		if ( $wikiPage->getTitle()->getContentModel() !== 'blog_post' ) {
			return;
		}
		$this->parseOnSave( $revisionRecord, $user );
	}

	/**
	 * @param UserIdentity $mentionedUser
	 * @param RevisionRecord $revisionRecord
	 * @param UserIdentity $actor
	 *
	 * @return void
	 * @throws \Exception
	 */
	protected function emitEvent( UserIdentity $mentionedUser, RevisionRecord $revisionRecord, UserIdentity $actor ) {
		$title = $this->titleFactory->castFromPageIdentity( $revisionRecord->getPage() );
		$this->notifier->emit(
			new UserMentionInEntity(
				$revisionRecord->getPageAsLinkTarget()->getText(), $mentionedUser, $actor, $title
			)
		);
	}

	public function onPageUndeleteComplete(
		ProperPageIdentity $page, Authority $restorer, string $reason, RevisionRecord $restoredRev,
		ManualLogEntry $logEntry, int $restoredRevisionCount, bool $created, array $restoredPageIds
	): void {
		$title = $this->titleFactory->castFromPageIdentity( $page );
		if ( !$title ) {
			return;
		}
		if ( $title->getContentModel() !== 'blog_post' ) {
			// For now only support wikitext
			return;
		}
		$revision = $this->revisionStore->getRevisionByTitle( $title );
		if ( !$revision ) {
			return;
		}
		$this->parseOnSave( $revision, null, false );
	}

}
