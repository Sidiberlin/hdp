<?php

namespace BlueSpice\ProDistributionConnector\Event;

use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;

class LoginNotifyFailKnown extends LoginNotifySuccess {

	/**
	 * @var int
	 */
	private $count;

	/**
	 * @param array $info
	 */
	public function __construct( array $info ) {
		parent::__construct( $info );
		$this->count = $info['count'] ?? 1;
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'login-notify-fail-known';
	}

	/**
	 * @param IChannel $forChannel
	 * @return Message
	 */
	public function getMessage( IChannel $forChannel ): Message {
		return Message::newFromKey( 'notification-known-header-login-fail', $this->count );
	}

	/**
	 * @param IChannel $forChannel
	 * @return array|\MWStake\MediaWiki\Component\Events\EventLink[]
	 */
	public function getLinks( IChannel $forChannel ): array {
		return [];
	}
}
