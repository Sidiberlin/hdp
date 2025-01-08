<?php

namespace MediaWiki\Extension\NotifyMe\MediaWiki\RunJobsTriggerHandler;

use MediaWiki\Extension\NotifyMe\Channel\Email\DigestCreator;
use MWStake\MediaWiki\Component\RunJobsTrigger\Interval\OnceADay;

class SendDailyDigest extends DigestHandler {
	/**
	 * @return string
	 */
	protected function getTargetDigestPeriod(): string {
		return DigestCreator::DIGEST_TYPE_DAILY;
	}

	/**
	 * @return string
	 */
	public function getKey() {
		return 'ext-notifyme-send-daily-digest';
	}

	/**
	 * @return OnceADay
	 */
	public function getInterval() {
		return new OnceADay();
	}
}
