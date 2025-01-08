<?php

namespace MediaWiki\Extension\NotifyMe\MediaWiki\RunJobsTriggerHandler;

use DateTime;
use MWStake\MediaWiki\Component\RunJobsTrigger\Interval;

class TwicePerHour implements Interval {

	/**
	 * @param DateTime $currentRunTimestamp
	 * @param array $options
	 *
	 * @return DateTime
	 */
	public function getNextTimestamp( $currentRunTimestamp, $options ) {
		$nextTS = clone $currentRunTimestamp;
		$nextTS->modify( '+30 minutes' );
		$nextTS->setTime( $nextTS->format( 'H' ), 0, 0 );

		return $nextTS;
	}
}
