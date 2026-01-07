<?php

namespace BlueSpice\ReadConfirmation\Event;

use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\PriorityEvent;
use MWStake\MediaWiki\Component\Events\TitleEvent;

class ConfirmationRequestEvent extends TitleEvent implements PriorityEvent {

	/**
	 * @var array
	 */
	protected $targetUsers;

	/**
	 * @param UserIdentity $actor
	 * @param Title $title
	 * @param array $targetUsers
	 */
	public function __construct( UserIdentity $actor, Title $title, array $targetUsers ) {
		parent::__construct( $actor, $title );
		$this->targetUsers = $targetUsers;
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'readconfirmation-event-confirmation-request-key-desc' );
	}

	/**
	 * @return string
	 */
	protected function getMessageKey(): string {
		return 'readconfirmation-event-confirmation-request';
	}

	/**
	 * @return array|null
	 */
	public function getPresetSubscribers(): ?array {
		return $this->targetUsers;
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'bs-rc-request';
	}

	/**
	 * @inheritDoc
	 */
	public function getLinksIntroMessage( IChannel $forChannel ): ?Message {
		return Message::newFromKey( 'readconfirmation-event-confirmation-links-intro' );
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
		return array_merge( parent::getArgsForTesting( $agent, $services, $extra ), [
			[ $extra['targetUser'] ?? $services->getUserFactory()->newFromName( 'WikiSysop' ) ]
		] );
	}
}
