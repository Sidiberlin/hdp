<?php

namespace MediaWiki\Extension\PageCheckout;

use ManualLogEntry;
use MediaWiki\Extension\PageCheckout\Entity\CheckoutEntity;
use User;

class SpecialLogLogger {
	/**
	 * @param CheckoutEntity $entity
	 * @param User $actor
	 * @param string $action
	 * @param string $comment
	 * @throws \MWException
	 */
	public function log( CheckoutEntity $entity, User $actor, $action, $comment ) {
		$logEntry = new ManualLogEntry( 'pagecheckout', $action );
		$logEntry->setPerformer( $actor );
		$logEntry->setTarget( $entity->getTitle() );
		$logEntry->setComment( $comment );
		$logEntry->setParameters( [
			'4::affectedUser' => $entity->getUser()->getName()
		] );

		$logEntry->insert();
	}
}
