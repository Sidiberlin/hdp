<?php

namespace MediaWiki\Extension\PageCheckout\Activity;

use MWException;
use Title;
use User;

class PageCheckInActivity extends CheckoutActivity {
	/**
	 * @param User $user
	 * @param Title $title
	 * @throws MWException
	 */
	protected function doAction( User $user, Title $title ) {
		$this->manager->checkIn( $title, $user );
	}
}
