<?php

namespace MediaWiki\Extension\UnifiedTaskOverview\Special;

use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;

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
		$this->requireNamedUser( 'unifiedtaskoverview-no-login-text' );
		$output = $this->getOutput();

		$output->addModules( 'ext.unifiedTaskOverview.specialPage' );

		$output->enableOOUI();
		$output->addHTML( Html::element( 'div', [ 'id' => 'unifiedTaskOverview-tiles' ] ) );
		$this->setHeaders();
	}
}
