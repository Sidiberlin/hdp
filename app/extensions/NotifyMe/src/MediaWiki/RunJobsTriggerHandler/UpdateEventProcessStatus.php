<?php

namespace MediaWiki\Extension\NotifyMe\MediaWiki\RunJobsTriggerHandler;

use MediaWiki\Extension\NotifyMe\Storage\NotificationStore;
use MWStake\MediaWiki\Component\ProcessManager\ProcessManager;
use MWStake\MediaWiki\Component\RunJobsTrigger\IHandler;
use Psr\Log\LoggerInterface;
use Status;

class UpdateEventProcessStatus implements IHandler {
	/** @var NotificationStore */
	private $notificationStore;
	/** @var ProcessManager */
	private $processManager;
	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param NotificationStore $notificationStore
	 * @param ProcessManager $processManager
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		NotificationStore $notificationStore, ProcessManager $processManager, LoggerInterface $logger
	) {
		$this->notificationStore = $notificationStore;
		$this->processManager = $processManager;
		$this->logger = $logger;
	}

	/**
	 * @return TwicePerHour
	 */
	public function getInterval() {
		return new TwicePerHour();
	}

	/**
	 * @return string
	 */
	public function getKey() {
		return 'ext-notifyme-update-event-process-status';
	}

	/**
	 * @return Status
	 */
	public function run() {
		$pending = $this->notificationStore->getPendingEventProcesses();

		foreach ( $pending as $eventProcess ) {
			if ( $this->processManager->getProcessStatus( $eventProcess['process'] ) === 'terminated' ) {
				$info = $this->processManager->getProcessInfo( $eventProcess['process'] );
				$exitMessage = $info->getExitCode();
				if ( $exitMessage === 0 ) {
					$this->notificationStore->updateEventProcessStatus( $eventProcess['id'], 'completed' );
				} else {
					$this->notificationStore->updateEventProcessStatus( $eventProcess['id'], 'failed' );
					$output = $info->getOutput()['error'] ?? '-';
					$this->logger->error(
						"Event process {$eventProcess['id']} failed with exit code {$exitMessage}: {$output}"
					);
				}
			}
		}

		return Status::newGood();
	}
}
