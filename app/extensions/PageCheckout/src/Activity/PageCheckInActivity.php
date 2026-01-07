<?php

namespace MediaWiki\Extension\PageCheckout\Activity;

use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MWException;

class PageCheckInActivity extends CheckoutActivity {
	/**
	 * @param User $user
	 * @param Title $title
	 * @throws MWException
	 */
	protected function doAction( User $user, Title $title ) {
		if ( !$this->manager->isCheckedOut( $title ) ) {
			return;
		}
		$this->manager->checkIn( $title );
	}
}
