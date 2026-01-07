<?php

namespace MediaWiki\Extension\ContentProvisioning\Special;

use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;

class ContentProvisioning extends SpecialPage {

	public function __construct() {
		// TODO: Fix permissions here, for some reason permission 'contentprovisioning-viewspecialpage' does not work
		//parent::__construct( 'ContentProvisioning', 'contentprovisioning-viewspecialpage' );
		parent::__construct( 'ContentProvisioning', 'edit' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->getOutput()->enableOOUI();
		$this->getOutput()->addModules( 'ext.contentProvisioning.special.overview' );

		$this->getOutput()->addHTML( Html::element( 'div', [ 'id' => 'contentProvisioning-overview' ] ) );
	}
}
