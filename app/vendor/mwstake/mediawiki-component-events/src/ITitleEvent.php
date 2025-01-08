<?php

namespace MWStake\MediaWiki\Component\Events;

use Title;

interface ITitleEvent extends INotificationEvent {
	/**
	 * @return Title
	 */
	public function getTitle(): Title;
}
