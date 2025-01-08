<?php

use MediaWiki\Extension\PageCheckout\CheckoutManager;
use MediaWiki\Extension\PageCheckout\Repo\CheckoutEventRepo;
use MediaWiki\Extension\PageCheckout\Repo\CheckoutRepo;
use MediaWiki\Extension\PageCheckout\SpecialLogLogger;

return [
	'PageCheckoutManager' => static function ( \MediaWiki\MediaWikiServices $services ) {
		return new CheckoutManager(
			// No likey :(
			RequestContext::getMain()->getUser(),
			new CheckoutRepo( $services->getDBLoadBalancer() ),
			new CheckoutEventRepo( $services->getDBLoadBalancer() ),
			new SpecialLogLogger()
		);
	}
];
