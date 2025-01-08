<?php

namespace BlueSpice\ReadConfirmation\RunJobsTriggerHandler;

use BlueSpice\ReadConfirmation\IMechanism;
use BlueSpice\ReadConfirmation\MechanismFactory;
use BlueSpice\RunJobsTriggerHandler;

class AutomaticReadConfirmationNotifier extends RunJobsTriggerHandler {

	/**
	 * @return \Status
	 */
	protected function doRun() {
		$status = \Status::newGood();
		$this->getReadConfirmationMechanism()->autoNotify();
		return $status;
	}

	/**
	 * @return IMechanism
	 */
	private function getReadConfirmationMechanism() {
		/** @var MechanismFactory $factory */
		$factory = $this->services->getService( 'BSReadConfirmationMechanismFactory' );

		return $factory->getMechanismInstance();
	}
}
