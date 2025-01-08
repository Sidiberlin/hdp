<?php

namespace MediaWiki\Extension\NotifyMe\Event;

use MWStake\MediaWiki\Component\Events\INotificationEvent;

interface NotifyAgentEvent extends INotificationEvent {
	// Events using this interface will generate a notification for the agent
}
