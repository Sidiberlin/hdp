<?php

namespace BlueSpice\ReadConfirmation\Event;

use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\BotAgent;

class ConfirmationRemindEvent extends ConfirmationRequestEvent {

	/**
	 * @param Title $title
	 * @param array $targetUsers
	 */
	public function __construct( Title $title, array $targetUsers ) {
		parent::__construct( new BotAgent(), $title, $targetUsers );
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'readconfirmation-event-confirmation-remind-key-desc' );
	}

	/**
	 * @return string
	 */
	protected function getMessageKey(): string {
		return 'readconfirmation-event-confirmation-remind';
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'bs-rc-remind';
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
			[ $extra['targetUser'] ?? $services->getUserFactory()->newFromName( 'WikiSysop' ) ]
		];
	}
}
