<?php

namespace AtMentions\Hook;

use AtMentions\Event\UserMention;
use AtMentions\Mention;
use AtMentions\MentionParser;
use AtMentions\MentionStore;
use HtmlArmor;
use ManualLogEntry;
use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\Hook\ParserBeforeInternalParseHook;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererEndHook;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\Hook\PageUndeleteCompleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\Notifier;

class ProcessMentions implements
	ParserBeforeInternalParseHook,
	PageSaveCompleteHook,
	PageMoveCompleteHook,
	PageDeleteCompleteHook,
	PageUndeleteCompleteHook,
	HtmlPageLinkRendererEndHook
{
	/** @var MentionParser */
	protected $parser;

	/** @var MentionStore */
	protected $store;

	/** @var RevisionStore */
	protected $revisionStore;

	/** @var Notifier */
	protected $notifier;

	/** @var TitleFactory */
	protected $titleFactory;

	/** @var UserFactory */
	protected $userFactory;

	/** @var bool */
	protected $replaceUserLinks = false;

	/**
	 * @param MentionParser $parser
	 * @param MentionStore $mentionStore
	 * @param RevisionStore $revisionStore
	 * @param Notifier $notifier
	 * @param TitleFactory $titleFactory
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		MentionParser $parser, MentionStore $mentionStore, RevisionStore $revisionStore,
		Notifier $notifier, TitleFactory $titleFactory, UserFactory $userFactory
	) {
		$this->parser = $parser;
		$this->store = $mentionStore;
		$this->revisionStore = $revisionStore;
		$this->notifier = $notifier;
		$this->titleFactory = $titleFactory;
		$this->userFactory = $userFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function onHtmlPageLinkRendererEnd( $linkRenderer, $target, $isKnown, &$text, &$attribs, &$ret ) {
		if ( !$this->replaceUserLinks ) {
			return true;
		}
		if ( $target->getNamespace() !== NS_USER ) {
			return true;
		}
		$pageTitle = RequestContext::getMain()->getTitle();
		if ( !$pageTitle ) {
			return true;
		}
		$existing = $this->store->forPage( $pageTitle )->query();
		if ( !$existing ) {
			return true;
		}
		$targetTitle = $this->titleFactory->newFromLinkTarget( $target );
		if ( $targetTitle->isSubpage() ) {
			return true;
		}
		$user = $this->userFactory->newFromName( $targetTitle->getText() );
		if ( $user === null ) {
			return true;
		}
		if ( !$this->isMentioned( $user, $existing ) ) {
			return true;
		}
		$origLabel = $text instanceof HtmlArmor ? $text->getHtml( $text ) : $text;
		if ( $origLabel === $targetTitle->getPrefixedText() || $origLabel === $targetTitle->getPrefixedDBkey() ) {
			$user = $this->userFactory->newFromName( $targetTitle->getText() );
			if ( $user ) {
				$text = $user->getRealName() ?: $user->getName();
			}
		}
		$attribs['style'] = 'background-color: #e5e4ff; border: 1px solid #acaeff;' .
			'padding: 0 2px; border-radius: 2px;';
		$attribs['class'] = 'at-mention';
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function onParserBeforeInternalParse( $mwParser, &$text, $stripState ) {
		$this->replaceUserLinks = false;
		if ( !$mwParser->getRevisionRecordObject() ) {
			// Just focus on parsing the actual revision
			return;
		}
		$title = $this->titleFactory->castFromPageIdentity( $mwParser->getRevisionRecordObject()->getPage() );
		if (
			!$title ||
			$title->getNamespace() === NS_MEDIAWIKI ||
			!$title->exists() ||
			!$title->isContentPage() ||
			$title->getContentModel() !== CONTENT_MODEL_WIKITEXT
		) {
			return;
		}

		// Set a flag to wrap the user link in the "mention html"
		$this->replaceUserLinks = true;
	}

	/**
	 * @inheritDoc
	 */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		if ( $wikiPage->getTitle()->getContentModel() !== CONTENT_MODEL_WIKITEXT ) {
			// For now only support wikitext
			return;
		}
		$this->parseOnSave( $revisionRecord, $user );
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 * @param UserIdentity|null $user
	 * @param bool|null $compareToPrevious
	 *
	 * @return void
	 */
	protected function parseOnSave(
		RevisionRecord $revisionRecord, ?UserIdentity $user = null, ?bool $compareToPrevious = true
	) {
		if ( $user === null ) {
			$user = $revisionRecord->getUser();
		}
		$currentText = $this->getRevisionText( $revisionRecord );
		$oldText = '';
		if ( $compareToPrevious ) {
			$oldRev = $this->revisionStore->getPreviousRevision( $revisionRecord );
			if ( $oldRev ) {
				$oldText = $this->getRevisionText( $oldRev );
			}
		}
		$existing = $this->store->forPage(
			$this->getTitleFromLinkTarget( $revisionRecord->getPageAsLinkTarget() )
		)->query();
		$difference = $this->parser->getMentionUserDifference( $oldText, $currentText );
		foreach ( $difference['removed'] as $mentionedUser ) {
			if ( !$this->isMentioned( $mentionedUser, $existing ) ) {
				continue;
			}
			$this->store->removeMention(
				$mentionedUser, $this->getTitleFromLinkTarget( $revisionRecord->getPageAsLinkTarget() )
			);
		}
		foreach ( $difference['added'] as $mentionedUser ) {
			if ( $this->isMentioned( $mentionedUser, $existing ) ) {
				// User is re-mentioned
				$this->store->updateMention( $mentionedUser, $revisionRecord, $user );
			} else {
				$this->store->addMention( $mentionedUser, $revisionRecord, $user );
			}

			$this->emitEvent( $mentionedUser, $revisionRecord, $user );
		}
	}

	/**
	 * @param RevisionRecord $revision
	 *
	 * @return string
	 */
	private function getRevisionText( RevisionRecord $revision ): string {
		$content = $revision->getContent( SlotRecord::MAIN );
		return $content->getText();
	}

	/**
	 * @param UserIdentity $user
	 * @param array $existing
	 *
	 * @return bool
	 */
	private function isMentioned( UserIdentity $user, array $existing ) {
		/** @var Mention $mention */
		foreach ( $existing as $mention ) {
			if ( $mention->getUser()->getName() === $user->getName() ) {
				return true;
			}
		}
		return false;
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
		$this->notifier->emit(
			new UserMention(
				$mentionedUser, $actor, $this->getTitleFromLinkTarget( $revisionRecord->getPageAsLinkTarget() )
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onPageDeleteComplete(
		ProperPageIdentity $page, Authority $deleter, string $reason, int $pageID,
		RevisionRecord $deletedRev, ManualLogEntry $logEntry, int $archivedRevisionCount
	) {
		$this->store->removeMentionsForPageId( $pageID );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		$title = Title::castFromLinkTarget( $new );
		$latestRev = $this->revisionStore->getRevisionByTitle( $title );
		$this->store->moveMentions( $latestRev );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageUndeleteComplete(
		ProperPageIdentity $page,
		Authority $restorer,
		string $reason,
		RevisionRecord $restoredRev,
		ManualLogEntry $logEntry,
		int $restoredRevisionCount,
		bool $created,
		array $restoredPageIds
	): void {
		$title = $this->titleFactory->castFromPageIdentity( $page );
		if ( !$title ) {
			return;
		}
		if ( $title->getContentModel() !== CONTENT_MODEL_WIKITEXT ) {
			// For now only support wikitext
			return;
		}
		$revision = $this->revisionStore->getRevisionByTitle( $title );
		if ( !$revision ) {
			return;
		}
		$this->parseOnSave( $revision, null, false );
	}

	/**
	 * @param LinkTarget $linkTarget
	 *
	 * @return Title
	 */
	private function getTitleFromLinkTarget( LinkTarget $linkTarget ): Title {
		return $this->titleFactory->newFromLinkTarget( $linkTarget );
	}
}
