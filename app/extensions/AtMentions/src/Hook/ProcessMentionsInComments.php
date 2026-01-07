<?php

namespace AtMentions\Hook;

use AtMentions\Event\UserMentionInEntity;
use AtMentions\MentionParser;
use Exception;
use MediaWiki\Extension\CommentStreams\AbstractComment;
use MediaWiki\Extension\CommentStreams\Comment;
use MediaWiki\Extension\CommentStreams\HookInterface\CommentStreamsInsertEntityHook;
use MediaWiki\Extension\CommentStreams\HookInterface\CommentStreamsUpdateEntityHook;
use MediaWiki\Extension\CommentStreams\Reply;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\Notifier;

class ProcessMentionsInComments implements
	CommentStreamsInsertEntityHook,
	CommentStreamsUpdateEntityHook
{

	public function __construct(
		private readonly MentionParser $parser,
		private readonly Notifier $notifier,
		private readonly TitleFactory $titleFactory
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onCommentStreamsInsertEntity(
		AbstractComment $entity, UserIdentity $actor, PageIdentity $associatedPage, string $type, string $wikitext
	) {
		$comment = $entity instanceof Reply ? $entity->getParent() : $entity;
		$this->notifyMentionedUsers(
			$comment, $this->getNewMentions( '', $wikitext ), $actor, $entity->getAssociatedPage()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onCommentStreamsUpdateEntity(
		AbstractComment $entity, UserIdentity $actor, string $oldText, string $newText
	) {
		$comment = $entity instanceof Reply ? $entity->getParent() : $entity;
		$this->notifyMentionedUsers(
			$comment, $this->getNewMentions( $oldText, $newText ), $actor, $entity->getAssociatedPage()
		);
	}

	/**
	 * @param Comment $comment
	 * @param array $mentioned
	 * @param UserIdentity $actor
	 * @param PageIdentity $title
	 * @return void
	 * @throws Exception
	 */
	private function notifyMentionedUsers(
		Comment $comment, array $mentioned, UserIdentity $actor, PageIdentity $title
	) {
		$title = $this->titleFactory->castFromPageIdentity( $title );
		foreach ( $mentioned as $mentionedUser ) {
			if ( $mentionedUser->getName() === $actor->getName() ) {
				continue;
			}
			$this->notifier->emit( new UserMentionInEntity( $comment->getTitle(), $mentionedUser, $actor, $title ) );
		}
	}

	/**
	 * @param string $oldText
	 * @param string $newText
	 * @return array
	 */
	private function getNewMentions( string $oldText, string $newText ) {
		$difference = $this->parser->getMentionUserDifference( $oldText, $newText );
		$mentions = [];
		foreach ( $difference['added'] as $mentionedUser ) {
			$mentions[] = $mentionedUser;
		}
		return $mentions;
	}
}
