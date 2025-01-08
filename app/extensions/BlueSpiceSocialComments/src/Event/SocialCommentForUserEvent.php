<?php

namespace BlueSpice\Social\Comments\Event;

use MediaWiki\User\UserIdentity;
use Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\EventLink;
use MWStake\MediaWiki\Component\Events\PriorityEvent;

class SocialCommentForUserEvent extends SocialCommentEvent implements PriorityEvent {

	/**
	 * @inheritDoc
	 */
	public function getLinks( IChannel $forChannel ): array {
		return [
			new EventLink(
				$this->getLinkTargetTitle()->getFullURL(),
				Message::newFromKey( 'bs-social-notification-comment-primary-link-label' )
			)
		];
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( "bs-social-comment-for-user-event-$this->action-desc" );
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage( IChannel $forChannel ): Message {
		return Message::newFromKey( "bs-social-comment-for-user-event-$this->action" )
			->params(
				$this->getAgent()->getName(),
				$this->getTitleAnchor(
					$this->doGetRelevantTitle(),
					$forChannel,
					Message::newFromKey( 'bs-social-notification-user-page-generic' )->text()
				)
			);
	}

	/**
	 * @return array|UserIdentity[]|null
	 */
	public function getPresetSubscribers(): ?array {
		$related = $this->doGetRelevantTitle();
		if ( $related && $related->getNamespace() === NS_USER ) {
			return [ $this->userFactory->newFromName( $related->getBaseText() ) ];
		}
		return [];
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'bs-social-comment-for-user-event';
	}
}
