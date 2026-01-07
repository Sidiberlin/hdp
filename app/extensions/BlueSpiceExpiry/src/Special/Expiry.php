<?php

namespace BlueSpice\Expiry\Special;

use MediaWiki\Html\Html;
use OOJSPlus\Special\OOJSGridSpecialPage;

class Expiry extends OOJSGridSpecialPage {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct( 'Expiry', 'edit' );
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute( $subPage ) {
		$out = $this->getOutput();
		$out->addModules( [ 'ext.bluespice.expiry.specialExpiry' ] );
		$out->addHTML( Html::element( 'div', [ 'id' => 'bs-expiry-special-expiry-container' ] ) );
	}
}
