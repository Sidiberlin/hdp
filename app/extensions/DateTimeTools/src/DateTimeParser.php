<?php

namespace MediaWiki\Extension\DateTimeTools;

use DateTime;

class DateTimeParser {

	/**
	 * @param string $text
	 *
	 * @return array
	 */
	public function parse( string $text ): array {
		$dates = [];
		$matches = [];
		preg_match_all( '/<datetime>(.*?)<\/datetime>/', $text, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$dt = $this->parseTimestamp( $match[1] );
			if ( !$dt ) {
				continue;
			}
			$dates[] = [
				'text' => $match[0],
				'datetime' => $dt,
			];
		}
		return $dates;
	}

	/**
	 * @param string $ts
	 *
	 * @return ?DateTime
	 */
	public function parseTimestamp( string $ts ): ?DateTime {
		$ts = trim( $ts );
		$time = strtotime( $ts );
		if ( $time === false ) {
			return null;
		}
		$date = new DateTime();
		$date->setTimestamp( $time );
		return $date;
	}

	/**
	 * @param DateTime $dt
	 *
	 * @return bool
	 */
	public function isMidnight( DateTime $dt ) {
		return $dt->format( 'H:i:s' ) === '00:00:00';
	}
}
