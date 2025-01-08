<?php

declare( strict_types=1 );

namespace MWStake\MediaWiki\Component\Events;

use DateTime;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;

/**
 * Convenience base class for notification events
 */
abstract class NotificationEvent implements INotificationEvent {
	/** @var UserIdentity */
	protected $agent;
	/** @var DateTime */
	protected $time;

	/**
	 * @param UserIdentity $agent
	 */
	public function __construct( UserIdentity $agent ) {
		$this->agent = $agent;
		$this->time = new DateTime();
	}

	/**
	 * @return UserIdentity
	 */
	public function getAgent(): UserIdentity {
		return $this->agent;
	}

	/**
	 * @return string
	 */
	public function getIcon(): string {
		return '';
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return new \RawMessage( $this->getKey() );
	}

	/**
	 * @return DateTime
	 */
	public function getTime(): DateTime {
		return $this->time;
	}

	/**
	 * @param DataTime $time
	 * @return void
	 */
	public function setTime( DateTime $time ): void {
		$this->time = $time;
	}

	/**
	 * @param IChannel $forChannel
	 * @return Message|null
	 */
	public function getLinksIntroMessage( IChannel $forChannel ): ?Message {
		return null;
	}

	/**
	 * @return array|null
	 */
	public function getPresetSubscribers(): ?array {
		// Notify all subscribed users
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function hasPriorityOver(): array {
		return [];
	}

	public function isBotAgent(): bool {
		return $this->getAgent() instanceof BotAgent;
	}

	/**
	 * @inheritDoc
	 */
	public static function getArgsForTesting(
		UserIdentity $agent, MediaWikiServices $services, array $extra = []
	): array {
		return [ $agent ];
	}
}
