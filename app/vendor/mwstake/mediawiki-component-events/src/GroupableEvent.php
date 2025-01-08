<?php

namespace MWStake\MediaWiki\Component\Events;

use Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;

interface GroupableEvent extends INotificationEvent {
	/**
	 * @param int $count
	 * @param IChannel $forChannel
	 *
	 * @return Message
	 */
	public function getGroupMessage( int $count, IChannel $forChannel ): Message;
}
