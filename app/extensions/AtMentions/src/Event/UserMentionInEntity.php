<?php

namespace AtMentions\Event;

use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;

class UserMentionInEntity extends UserMention {

	public function __construct(
		private readonly string $entityTitle,
		UserIdentity $mentionedUser, UserIdentity $agent, Title $title
	) {
		parent::__construct( $mentionedUser, $agent, $title );
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'at-mentions-mention-in-entity';
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'at-mentions-mention-in-entity-notification-key' );
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage( IChannel $forChannel ): Message {
		return Message::newFromKey( 'at-mentions-mention-in-entity-notification-message' )->params(
			$this->getAgent()->getName(),
			$this->getTitleAnchor( $this->getTitle(), $forChannel ),
			$this->entityTitle
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getLinks( IChannel $forChannel ): array {
		return [];
	}

	/**
	 * @param UserIdentity $agent
	 * @param MediaWikiServices $services
	 * @param array $extra
	 * @return array
	 */
	public static function getArgsForTesting(
		UserIdentity $agent, MediaWikiServices $services, array $extra = []
	): array {
		// Not testable
		return [];
	}
}
