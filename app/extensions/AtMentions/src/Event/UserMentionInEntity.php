<?php

namespace AtMentions\Event;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use Title;

class UserMentionInEntity extends UserMention {

	/** @var string */
	private $text;

	/** @var Title|null */
	private $relatedTitle;

	/**
	 * @param UserIdentity $mentionedUser
	 * @param UserIdentity $agent
	 * @param Title $entityTitle
	 * @param string $text
	 * @param Title|null $relatedTitle
	 */
	public function __construct(
		UserIdentity $mentionedUser, UserIdentity $agent, Title $entityTitle,
		string $text, ?Title $relatedTitle = null
	) {
		parent::__construct( $mentionedUser, $agent, $entityTitle );
		$this->text = $text;
		$this->relatedTitle = $relatedTitle;
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'at-mentions-mention-in-entity';
	}

	/**
	 * @return Title
	 */
	public function getTitle(): Title {
		return $this->relatedTitle ?? parent::getTitle();
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'at-mentions-mention-in-entity-notification-key' );
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage( IChannel $forChannel ): Message {
		return Message::newFromKey( 'at-mentions-mention-in-entity-notification-message' )->params(
			$this->getAgent()->getName(),
			$this->getTitleAnchor( $this->getTitle(), $forChannel ),
			$this->getSnippet()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getLinks( IChannel $forChannel ): array {
		return [];
	}

	/**
	 * @return string
	 */
	private function getSnippet(): string {
		if ( !$this->text ) {
			return '';
		}
		// TODO: Get snippet around the user link, just some random stuff for now
		$suffix = '...';
		$len = min( strlen( $this->text ), 100 );
		if ( strlen( $this->text ) < 100 ) {
			$suffix = '';
		}
		return substr( $this->text, 0, $len ) . $suffix;
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
		// Not testable
		return [];
	}
}
