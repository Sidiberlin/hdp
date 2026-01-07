<?php

namespace BlueSpice\Expiry\Event;

use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentity;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\BotAgent;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\TitleEvent;

class ExpiryToday extends TitleEvent {

	/** @var string */
	protected $comment;

	/**
	 * @param PageIdentity $title
	 * @param string $comment
	 */
	public function __construct( PageIdentity $title, string $comment ) {
		parent::__construct( new BotAgent(), $title );
		$this->comment = $comment;
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'bs-expiry-event-today-desc' );
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage( IChannel $forChannel ): Message {
		$msgKey = $this->getMessageKey();
		if ( $this->comment ) {
			return Message::newFromKey( $msgKey . '-with-comment' )->params(
				$this->getTitleAnchor( $this->getTitle(), $forChannel ),
				$this->comment
			);
		}
		return Message::newFromKey( $msgKey )->params( $this->getTitleAnchor( $this->getTitle(), $forChannel ) );
	}

	/**
	 * @return string
	 */
	public function getMessageKey(): string {
		return 'bs-expiry-event-today';
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
		return 'bs-expiry-today';
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
		return [
			$extra['title'] ?? $services->getTitleFactory()->newMainPage(),
			'dummy comment'
		];
	}
}
