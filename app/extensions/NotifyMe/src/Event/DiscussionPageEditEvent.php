<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\NotifyMe\Event;

use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\GroupableEvent;
use MWStake\MediaWiki\Component\Events\PriorityEvent;
use MWStake\MediaWiki\Component\Events\TitleEvent;
use User;

class DiscussionPageEditEvent extends TitleEvent implements GroupableEvent, PriorityEvent {

	/** @var User|null */
	private $targetUser;

	/**
	 * @param UserFactory $userFactory
	 * @param UserIdentity $agent
	 * @param PageIdentity $title
	 */
	public function __construct( UserFactory $userFactory, UserIdentity $agent, PageIdentity $title ) {
		parent::__construct( $agent, $title );
		$this->targetUser = $userFactory->newFromName( $title->getDBkey() );
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'discussion-edit';
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'notifyme-event-discussion-edit-key-desc' );
	}

	/**
	 * @return string
	 */
	protected function getMessageKey(): string {
		return 'notifyme-event-discussion-edit';
	}

	/**
	 * @inheritDoc
	 */
	public function getLinksIntroMessage( IChannel $forChannel ): ?Message {
		return Message::newFromKey( 'notifyme-links-intro-discussion-edit' );
	}

	/**
	 * @inheritDoc
	 */
	public function getPresetSubscribers(): ?array {
		if ( !$this->targetUser || !$this->targetUser->isRegistered() ) {
			// Send to no-one
			return [];
		}

		return [ $this->targetUser ];
	}

	/**
	 * @inheritDoc
	 */
	public function getGroupMessage( int $count, IChannel $forChannel ): Message {
		return Message::newFromKey( 'notifyme-event-discussion-edit-group' )
			->params( $this->getTitleAnchor( $this->getTitle(), $forChannel ), $count );
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
		$title = $extra['title'] ?? null;
		if ( !$title || $title->getNamespace() !== NS_USER_TALK ) {
			if ( isset( $extra['targetUser'] ) ) {
				$title = $services->getTitleFactory()->makeTitle( NS_USER_TALK, $extra['targetUser']->getName() );
			} else {
				$title = $services->getTitleFactory()->makeTitle( NS_USER_TALK, 'WikiSysop' );
			}
		}
		return [ $agent, $title ];
	}
}
