<?php

namespace MediaWiki\Extension\PageCheckout\Activity;

use MediaWiki\Extension\Workflows\Activity\ExecutionStatus;
use MediaWiki\Extension\Workflows\WorkflowContext;
use Message;
use MWException;
use Title;
use User;

class PageCheckoutActivity extends CheckoutActivity {
	/** @var bool */
	private $force;
	/** @var string */
	private $genericUsername = 'Mediawiki default';

	/**
	 * @inheritDoc
	 */
	public function execute( $data, WorkflowContext $context ): ExecutionStatus {
		$this->force = isset( $data['force'] ) ? (bool)$data['force'] : false;
		return parent::execute( $data, $context );
	}

	/**
	 * @param User $user
	 * @param Title $title
	 * @throws MWException
	 */
	protected function doAction( User $user, Title $title ) {
		if ( $this->force && $this->manager->isCheckedOut( $title ) ) {
			$this->manager->clearCheckout( $title );
		}
		$payload = [
			'workflowId' => $this->workflowContext->getWorkflowId()->toString(),
		];
		if ( $user->getName() === $this->genericUsername ) {
			$payload['alertText'] = Message::newFromKey(
				'page-checkout-workflow-activity-checkout-reason'
			)->text();
		} else {
			$payload['comment'] = Message::newFromKey(
				'page-checkout-workflow-activity-checkout-non-generic-reason'
			)->text();
		}
		$this->manager->checkout( $title, $user, $payload );
	}
}
