<?php

namespace MediaWiki\Extension\NotifyMe\MediaWiki\Hook;

use MediaWiki\Extension\NotifyMe\MediaWiki\RunJobsTriggerHandler\SendDailyDigest;
use MediaWiki\Extension\NotifyMe\MediaWiki\RunJobsTriggerHandler\SendWeeklyDigest;

class RegisterDigestSenders {
	/**
	 * @param array &$handlers
	 *
	 * @return bool
	 */
	public static function callback( &$handlers ) {
		$handlers['ext-notifyme-send-digest-daily'] = [
			'class' => SendDailyDigest::class,
			'services' => [ 'NotifyMe.Store', 'NotifyMe.ChannelFactory', 'NotifyMe.Logger' ],
		];

		$handlers['ext-notifyme-send-digest-weekly'] = [
			'class' => SendWeeklyDigest::class,
			'services' => [ 'NotifyMe.Store', 'NotifyMe.ChannelFactory', 'NotifyMe.Logger' ],
		];

		return true;
	}
}
