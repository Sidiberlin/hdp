<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\NotifyMe\Event;

use Message;

class UserGroupRemovedEvent extends UserGroupAddedEvent {
	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'user-group-removed';
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'notifyme-event-user-group-removed-key-desc' );
	}

	/**
	 * @return string
	 */
	protected function getMessageKey(): string {
		return 'notifyme-event-group-removed';
	}
}
