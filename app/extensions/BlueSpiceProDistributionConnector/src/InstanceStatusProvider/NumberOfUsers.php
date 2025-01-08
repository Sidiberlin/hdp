<?php

namespace BlueSpice\ProDistributionConnector\InstanceStatusProvider;

use BlueSpice\InstanceStatus\IStatusProvider;
use BlueSpice\ProDistributionConnector\UserCounter;
use Message;

class NumberOfUsers implements IStatusProvider {
	/** @var UserCounter */
	private $counter;

	/**
	 * @param UserCounter $counter
	 */
	public function __construct( UserCounter $counter ) {
		$this->counter = $counter;
	}

	/**
	 * @return string
	 */
	public function getLabel(): string {
		return Message::newFromKey( 'bs-pro-distribution-instance-status-user-quota-label' )->text();
	}

	/**
	 * @return string
	 */
	public function getValue(): string {
		return $this->counter->getSentenceHtml();
	}

	/**
	 * @return string
	 */
	public function getIcon(): string {
		return 'userAvatar';
	}

	/**
	 * @return int
	 */
	public function getPriority(): int {
		return 10;
	}

}
