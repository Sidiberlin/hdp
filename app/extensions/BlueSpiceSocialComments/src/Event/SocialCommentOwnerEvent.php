<?php

namespace BlueSpice\Social\Comments\Event;

use Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\PriorityEvent;
use User;

class SocialCommentOwnerEvent extends SocialCommentEvent implements PriorityEvent {

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( "bs-social-comment-event-own-$this->action-desc" );
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage( IChannel $forChannel ): Message {
		return Message::newFromKey( "bs-social-comment-event-own-$this->action" )
			->params( $this->getAgent()->getName(), $this->getTitleAnchor( $this->doGetRelevantTitle(), $forChannel ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getPresetSubscribers(): ?array {
		if ( $this->parentEntityOwner instanceof User ) {
			return [ $this->parentEntityOwner->getId() => $this->parentEntityOwner ];
		}

		return [];
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'bs-social-comment-own-event';
	}
}
