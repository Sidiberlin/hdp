<?php

namespace SimpleTasks\Event;

use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentity;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\PriorityEvent;
use MWStake\MediaWiki\Component\Events\TitleEvent;

class TaskEvent extends TitleEvent implements PriorityEvent {

	/**
	 * @param UserIdentity $agent
	 * @param PageIdentity $page
	 * @param UserIdentity $targetUser
	 * @param string $text
	 * @param string|null $dueDate
	 */
	public function __construct(
		UserIdentity $agent, PageIdentity $page,
		private readonly UserIdentity $targetUser,
		private readonly string $text,
		private readonly ?string $dueDate
	) {
		parent::__construct( $agent, $page );
	}

	/**
	 * @inheritDoc
	 */
	public function getKey(): string {
		return 'simple-tasks-task';
	}

	/**
	 * @inheritDoc
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'simple-tasks-notif-key' );
	}

	/**
	 * @inheritDoc
	 */
	public function getPresetSubscribers(): array {
		return [ $this->targetUser ];
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage( IChannel $forChannel ): Message {
		if ( $this->dueDate ) {
			return Message::newFromKey( 'simple-tasks-notif-message-with-due-date' )->params(
				$this->text,
				$this->dueDate
			);
		}
		return Message::newFromKey( 'simple-tasks-notif-message' )->params(
			$this->text
		);
	}

	/**
	 * @inheritDoc
	 */
	public function hasPriorityOver(): array {
		return [ 'at-mentions-mention' ];
	}

	/**
	 * @inheritDoc
	 */
	public static function getArgsForTesting(
		UserIdentity $agent, MediaWikiServices $services, array $extra = []
	): array {
		$target = $extra['targetUser'] ?? $services->getUserFactory()->newFromName( 'WikiSysop' );
		return [
			$agent,
			$extra['title'],
			$target,
			$extra['text'] ?? 'Some task',
			null, $services->getContentLanguage()->userDate(
				( new \DateTime() )->modify( '+1 day' )->getTimestamp(), $target
			),
		];
	}
}
