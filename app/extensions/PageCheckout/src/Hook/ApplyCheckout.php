<?php

namespace MediaWiki\Extension\PageCheckout\Hook;

use MediaWiki\Extension\PageCheckout\CheckoutManager;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use Message;
use Title;
use User;

class ApplyCheckout implements GetUserPermissionsErrorsHook {
	/** @var string[] Permissions to lock */
	private $permissions = [
		'edit',
		'move',
		'delete',
		'review'
	];

	/** @var CheckoutManager */
	private $manager;

	/**
	 * ApplyCheckout constructor.
	 * @param CheckoutManager $manager
	 */
	public function __construct( CheckoutManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param string &$result
	 * @return bool
	 */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( !in_array( $action, $this->permissions ) ) {
			return true;
		}
		if ( !$this->manager->isCheckedOut( $title ) ) {
			return true;
		}
		$entity = $this->manager->getCheckoutEntity( $title );
		if ( $entity->getUser()->getId() === $user->getId() ) {
			return true;
		}

		$result = Message::newFromKey(
			'pagecheckout-lockdown-reason',
			Message::newFromKey( "right-$action" )->text(),
			$entity->getUser()->getName()
		);
		return false;
	}
}
