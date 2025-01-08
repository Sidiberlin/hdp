<?php

namespace BlueSpice\InstanceStatus\InstanceStatusProvider;

use BlueSpice\InstanceStatus\IStatusProvider;
use Config;
use ConfigFactory;
use Message;

class BlueSpiceVersion implements IStatusProvider {
	/** @var Config */
	private $config;

	/**
	 * @param ConfigFactory $configFactory
	 */
	public function __construct( ConfigFactory $configFactory ) {
		$this->config = $configFactory->makeConfig( 'bsg' );
	}

	/**
	 * @return string
	 */
	public function getLabel(): string {
		return Message::newFromKey( 'bs-instancestatus-instance-status-bs-version-label' )->text();
	}

	/**
	 * @return string
	 */
	public function getValue(): string {
		$info = $this->config->get( 'BlueSpiceExtInfo' );
		return $info['name'] . ' ' . $info['version'];
	}

	/**
	 * @return string
	 */
	public function getIcon(): string {
		return 'bluespice';
	}

	/**
	 * @return int
	 */
	public function getPriority(): int {
		return 30;
	}
}
