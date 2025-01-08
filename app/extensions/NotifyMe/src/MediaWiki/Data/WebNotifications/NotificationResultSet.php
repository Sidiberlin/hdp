<?php

namespace MediaWiki\Extension\NotifyMe\MediaWiki\Data\WebNotifications;

use MWStake\MediaWiki\Component\DataStore\Record;
use MWStake\MediaWiki\Component\DataStore\ResultSet;

class NotificationResultSet extends ResultSet {
	/**
	 *
	 * @var int
	 */
	protected $processedCount = 0;

	/**
	 *
	 * @param Record[] $records
	 * @param int $total
	 * @param int $processedCount
	 */
	public function __construct( $records, $total, int $processedCount ) {
		parent::__construct( $records, $total );
		$this->processedCount = $processedCount;
	}

	/**
	 * @return int
	 */
	public function getProcessedCount(): int {
		return $this->processedCount;
	}
}
