<?php

namespace MediaWiki\Extension\NotifyMe\MediaWiki\RunJobsTriggerHandler;

use MediaWiki\Extension\NotifyMe\Channel\Email\DigestCreator;
use MWStake\MediaWiki\Component\RunJobsTrigger\Interval\OnceAWeek;

class SendWeeklyDigest extends DigestHandler {
	/**
	 * @return string
	 */
	protected function getTargetDigestPeriod(): string {
		return DigestCreator::DIGEST_TYPE_WEEKLY;
	}

	/**
	 * @return string
	 */
	public function getKey() {
		return 'ext-notifyme-send-weekly-digest';
	}

	/**
	 * @return OnceAWeek
	 */
	public function getInterval() {
		return new OnceAWeek();
	}
}
