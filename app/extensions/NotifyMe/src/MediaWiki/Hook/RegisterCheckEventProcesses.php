<?php

namespace MediaWiki\Extension\NotifyMe\MediaWiki\Hook;

use MediaWiki\Extension\NotifyMe\MediaWiki\RunJobsTriggerHandler\UpdateEventProcessStatus;

class RegisterCheckEventProcesses {
	/**
	 * @param array &$handlers
	 *
	 * @return bool
	 */
	public static function callback( &$handlers ) {
		$handlers['ext-notifyme-update-event-process-status'] = [
			'class' => UpdateEventProcessStatus::class,
			'services' => [ 'NotifyMe.Store', 'ProcessManager', 'NotifyMe.Logger' ],
		];

		return true;
	}
}
