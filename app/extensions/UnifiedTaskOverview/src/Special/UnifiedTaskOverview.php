<?php

namespace MediaWiki\Extension\UnifiedTaskOverview\Special;

use Html;
use SpecialPage;

class UnifiedTaskOverview extends SpecialPage {

	/**
	 */
	public function __construct() {
		parent::__construct( 'UnifiedTaskOverview' );
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		$output = $this->getOutput();

		$output->addModules( 'ext.unifiedTaskOverview.specialPage' );

		$output->enableOOUI();
		$output->addHTML( Html::element( 'div', [ 'id' => 'unifiedTaskOverview-tiles' ] ) );
		$this->setHeaders();
	}
}
