<?php

namespace BlueSpice\Expiry\Event;

use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;

class ExpiryInOneWeek extends ExpiryToday {

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'bs-expiry-event-one-week-desc' );
	}

	/**
	 * @return string
	 */
	public function getMessageKey(): string {
		return 'bs-expiry-event-one-week';
	}

	/**
	 * @inheritDoc
	 */
	public function getLinksIntroMessage( IChannel $forChannel ): ?Message {
		return Message::newFromKey( 'ext-notifyme-notification-generic-links-intro' );
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'bs-expiry-one-week';
	}
}
