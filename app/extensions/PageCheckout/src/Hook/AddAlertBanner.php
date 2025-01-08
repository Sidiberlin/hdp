<?php

namespace MediaWiki\Extension\PageCheckout\Hook;

class AddAlertBanner {

	/**
	 * @param array &$handlerSpecs
	 */
	public function onMWStakeAlertBannersRegisterProviders( &$handlerSpecs ) {
		$handlerSpecs['pagecheckout'] = [
			'class' => "\\MediaWiki\\Extension\\PageCheckout\\AlertProvider\\PageCheckout",
			'services' => [ 'PageCheckoutManager' ]
		];
	}
}
