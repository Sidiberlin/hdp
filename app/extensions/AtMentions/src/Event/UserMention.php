<?php

namespace AtMentions\Event;

use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\PriorityEvent;
use MWStake\MediaWiki\Component\Events\TitleEvent;

class UserMention extends TitleEvent implements PriorityEvent {

	/** @var UserIdentity */
	private $mentionedUser;

	/**
	 * @param UserIdentity $mentionedUser
	 * @param UserIdentity $agent
	 * @param Title $title
	 */
	public function __construct( UserIdentity $mentionedUser, UserIdentity $agent, Title $title ) {
		parent::__construct( $agent, $title );
		$this->mentionedUser = $mentionedUser;
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'at-mentions-mention';
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'at-mentions-mention-notification-key' );
	}

	/**
	 * @return UserIdentity[]
	 */
	public function getPresetSubscribers(): array {
		return [ $this->mentionedUser ];
	}

	/**
	 * @return string
	 */
	protected function getMessageKey(): string {
		return 'at-mentions-mention-notification-message';
	}

	/**
	 * @inheritDoc
	 */
	public function getLinks( IChannel $forChannel ): array {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public static function getArgsForTesting(
		UserIdentity $agent, MediaWikiServices $services, array $extra = []
	): array {
		$targetUser = $extra['targetUser'] ?? $services->getUserFactory()->newFromName( 'WikiSysop' );
		$title = $extra['title'] ?? $services->getTitleFactory()->newMainPage();
		return [ $targetUser, $agent, $title ];
	}
}
