<?php

namespace BlueSpice\Expiry\RunJobsTriggerHandler\SendNotification;

use BlueSpice\Expiry\Data\Record;
use BlueSpice\Expiry\Event\ExpiryInOneWeek;
use BlueSpice\Expiry\RunJobsTriggerHandler\SendNotification;
use BlueSpice\RunJobsTriggerHandler\Interval\OnceAWeek;
use DateInterval;
use DateTime;
use DateTimeZone;
use MediaWiki\Title\Title;
use MWStake\MediaWiki\Component\Events\Notifier;

class Weekly extends SendNotification {

	/**
	 *
	 * @return OnceAWeek
	 */
	public function getInterval() {
		return new OnceAWeek();
	}

	/**
	 * @param Title $title
	 * @param Record $record
	 * @param Notifier $notifier
	 * @throws \Exception
	 */
	protected function sendNotifications( Title $title, Record $record, Notifier $notifier ) {
		$comment = $record->get( Record::COMMENT, '' );
		$event = new ExpiryInOneWeek( $title, $comment );
		$notifier->emit( $event );
	}

	/**
	 * @return Title[]
	 */
	protected function getExpiredTitles() {
		$timezone = $this->config->get( 'Localtimezone' );
		if ( !$timezone ) {
			$timezone = 'UTC';
		}

		$from = new DateTime( 'now', new DateTimeZone( $timezone ) );
		$from->add( DateInterval::createFromDateString( '1 week' ) );
		$from->setTime( 0, 0, 0 );
		$to = clone $from;

		$from->sub( DateInterval::createFromDateString( '1 day' ) );
		$to->add( DateInterval::createFromDateString( '1 day' ) );

		return $this->factory->getExpiredTitles( $from, $to );
	}
}
