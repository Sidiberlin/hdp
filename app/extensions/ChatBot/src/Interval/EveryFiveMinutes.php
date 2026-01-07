<?php

namespace ChatBot\Interval;

use DateTime;
use MWStake\MediaWiki\Component\RunJobsTrigger\Interval;

class EveryFiveMinutes implements Interval {

	/**
	 * @param DateTime $currentRunTimestamp
	 * @param array $options
	 *
	 * @return DateTime
	 * @throws \DateMalformedStringException
	 */
	public function getNextTimestamp( $currentRunTimestamp, $options ): DateTime {
		$nextTimestamp = clone $currentRunTimestamp;
		$nextTimestamp->modify( '+5 minutes' );

		return $nextTimestamp;
	}
}
