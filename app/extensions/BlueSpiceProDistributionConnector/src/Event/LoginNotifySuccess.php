<?php

namespace BlueSpice\ProDistributionConnector\Event;

use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\BotAgent;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\NotificationEvent;

class LoginNotifySuccess extends NotificationEvent {

	/**
	 * @var UserIdentity
	 */
	private $target;

	/**
	 * @param array $info
	 */
	public function __construct( array $info ) {
		parent::__construct( new BotAgent() );
		$this->target = $info['agent'];
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'login-notify-success';
	}

	/**
	 * @return array|UserIdentity[]|null
	 */
	public function getPresetSubscribers(): ?array {
		return [ $this->target ];
	}

	/**
	 * @param IChannel $forChannel
	 * @return Message
	 */
	public function getMessage( IChannel $forChannel ): Message {
		return Message::newFromKey( 'notification-header-login-success', $this->getAgent()->getName() );
	}

	/**
	 * @param IChannel $forChannel
	 * @return array|\MWStake\MediaWiki\Component\Events\EventLink[]
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
		$targetUser = $extra['targetUser'] ?? $agent;
		return [ [ 'agent' => $targetUser ] ];
	}
}
