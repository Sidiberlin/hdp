<?php

namespace MediaWiki\Extension\PageCheckout\Workflows\Consumer;

use EventSauce\EventSourcing\Consumer;
use EventSauce\EventSourcing\Message;
use MediaWiki\Extension\PageCheckout\CheckoutManager;
use MediaWiki\Extension\Workflows\Storage\Event\WorkflowAborted;
use MediaWiki\MediaWikiServices;
use Message as MediaWikiMessage;
use MWException;
use Title;

class ClearCheckoutOnAbort implements Consumer {
	/** @var CheckoutManager */
	private $checkoutManager;

	/**
	 * @param CheckoutManager $checkoutManager
	 */
	public function __construct( CheckoutManager $checkoutManager ) {
		$this->checkoutManager = $checkoutManager;
	}

	/**
	 * @param Message $message
	 * @throws MWException
	 */
	public function handle( Message $message ) {
		$event = $message->event();
		if ( !$event instanceof WorkflowAborted ) {
			return;
		}
		// TODO: Cannot be injected - circular dependency
		$workflowFactory = MediaWikiServices::getInstance()->getService( 'WorkflowFactory' );
		$workflowId = $message->aggregateRootId();
		$workflow = $workflowFactory->getWorkflow( $workflowId );
		$page = $workflow->getContext()->getContextPage();
		if ( !$page instanceof Title ) {
			return;
		}
		$entity = $this->checkoutManager->getCheckoutEntity( $page );
		if ( !$entity ) {
			return;
		}
		$payload = $entity->getPayload();
		if ( isset( $payload['workflowId'] ) && $payload['workflowId'] === $workflowId->toString() ) {
			$this->checkoutManager->checkIn(
				$page,
				MediaWikiMessage::newFromKey( "page-checkout-workflow-clear-checkout" )->text()
			);
		}
	}
}
