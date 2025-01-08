<?php

namespace BlueSpice\Expiry\RunJobsTriggerHandler;

use BlueSpice\Expiry\Data\Record;
use BlueSpice\Expiry\Factory;
use BlueSpice\RunJobsTriggerHandler;
use BlueSpice\UtilityFactory;
use Config;
use MediaWiki\MediaWikiServices;
use MWStake\MediaWiki\Component\Events\Notifier;
use Status;
use Title;
use Wikimedia\Rdbms\LoadBalancer;

abstract class SendNotification extends RunJobsTriggerHandler {

	/**
	 *
	 * @var Factory
	 */
	protected $factory = null;

	/**
	 *
	 * @var UtilityFactory
	 */
	protected $util = null;

	/** @var Title[] */
	protected $expiredTitles = [];

	/**
	 *
	 * @param Config $config
	 * @param LoadBalancer $loadBalancer
	 * @param Factory $factory
	 * @param UtilityFactory $util
	 */
	public function __construct( $config, $loadBalancer, $factory, $util ) {
		parent::__construct( $config, $loadBalancer );
		$this->factory = $factory;
		$this->util = $util;
	}

	/**
	 * @param Config $config
	 * @param LoadBalancer $loadBalancer
	 * @param Factory|null $factory
	 * @param UtilityFactory|null $util
	 * @return RunJobsTriggerHandler
	 */
	public static function factory( $config, $loadBalancer, Factory $factory = null, UtilityFactory $util = null ) {
		if ( !$factory ) {
			$factory = MediaWikiServices::getInstance()->getService(
				'BSExpiryFactory'
			);
		}
		if ( !$util ) {
			$util = MediaWikiServices::getInstance()->getService(
				'BSUtilityFactory'
			);
		}
		return new static( $config, $loadBalancer, $factory, $util );
	}

	/**
	 * @return Status
	 */
	protected function doRun() {
		$status = Status::newGood();

		$notifier = $this->services->getService( 'MWStake.Notifier' );
		$this->expiredTitles = $this->getExpiredTitles();
		if ( empty( $this->expiredTitles ) ) {
			return $status;
		}

		foreach ( $this->expiredTitles as $title ) {
			$expiry = $this->factory->newFromTitle( $title );
			$this->sendNotifications( $title, $expiry, $notifier );
		}

		return $status;
	}

	/**
	 * @return Title[]
	 */
	protected function getExpiredTitles() {
		return $this->factory->getExpiredTitles( null );
	}

	/**
	 * @param Title $title
	 * @param Record $record
	 * @param Notifier $notifier
	 */
	abstract protected function sendNotifications( Title $title, Record $record, Notifier $notifier );
}
