<?php

namespace MediaWiki\Extension\NotifyMe;

use Exception;
use Message;
use MWStake\MediaWiki\Component\Events\INotificationEvent;

class BucketProvider {

	/**
	 * @var array
	 */
	private $bucketDefinitions;

	/**
	 * @var EventProvider
	 */
	private $eventProvider;

	/**
	 * @param array $bucketDefinitions
	 * @param EventProvider $eventProvider
	 */
	public function __construct( array $bucketDefinitions, EventProvider $eventProvider ) {
		$this->bucketDefinitions = $bucketDefinitions;
		$this->eventProvider = $eventProvider;
	}

	/**
	 * @return array
	 */
	public function getBuckets(): array {
		return array_keys( $this->bucketDefinitions );
	}

	/**
	 * @return array
	 */
	public function getEventTypes(): array {
		return array_keys( $this->eventProvider->getRegisteredEvents() );
	}

	/**
	 * @param string $bucket
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function isMandatory( string $bucket ): bool {
		$bucketDef = $this->bucketDefinitions[$bucket] ?? null;
		if ( $bucketDef === null ) {
			throw new Exception( "Invalid bucket \"{$bucket}\"" );
		}
		return $bucketDef['mandatory'] ?? false;
	}

	/**
	 * @param INotificationEvent $event
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function hasMandatoryBuckets( INotificationEvent $event ): bool {
		$eventBuckets = $this->getEventBuckets( $event );
		foreach ( $eventBuckets as $bucket ) {
			if ( $this->isMandatory( $bucket ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function getBucketLabels(): array {
		$labels = [];
		foreach ( $this->bucketDefinitions as $bucket => $bucketDef ) {
			$labels[$bucket] = [
				'label' => Message::newFromKey( $bucketDef['label'] )->text(),
				'description' => Message::newFromKey( $bucketDef['description'] )->text(),
				'mandatory' => $this->isMandatory( $bucket ),
			];
		}
		return $labels;
	}

	/**
	 * @param INotificationEvent $event
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getEventBuckets( INotificationEvent $event ): array {
		$events = $this->eventProvider->getRegisteredEvents();
		if ( !isset( $events[$event->getKey()] ) ) {
			throw new \Exception( "Event with key {$event->getKey()} is not registered" );
		}
		return $events[$event->getKey()]['buckets'] ?? [];
	}
}
