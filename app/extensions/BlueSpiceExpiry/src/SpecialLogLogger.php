<?php

namespace BlueSpice\Expiry;

use ManualLogEntry;
use MWException;
use Title;
use User;

class SpecialLogLogger {
	public const LOG_ACTION_EXPIRED = 'expired';
	public const LOG_ACTION_CHANGE_DATE = 'change-date';
	public const LOC_ACTION_DELETE = 'delete';

	/**
	 * @param User $user
	 * @param Title $title
	 * @param string $action
	 * @param string $comment
	 * @throws MWException
	 */
	public function log( User $user, Title $title, $action, $comment = '' ) {
		$logEntry = new ManualLogEntry( 'bs-expiry', $action );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( $title );
		$logEntry->setComment( $comment );

		$logEntry->insert();
	}
}
