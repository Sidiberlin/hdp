<?php

namespace MediaWiki\Extension\EnhancedStandardUIs\Special;

use ExtensionRegistry;
use Html;
use SpecialPage;

class EnhancedFilelist extends SpecialPage {

	/**
	 *
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct( 'EnhancedFilelist' );
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function execute( $subPage ) {
		$out = $this->getOutput();

		$this->setHeaders();
		$this->outputHeader();
		$out->setPreventClickjacking( false );
		$this->setHeaders();
		$out->enableOOUI();

		$out->addModules( [ 'ext.enhancedstandarduis.special.filelist' ] );
		$modules = ExtensionRegistry::getInstance()->getAttribute(
			'EnhancedStandardUIsFilelistPluginModules'
		);
		$out->addModules( $modules );

		$html = Html::element(
			'div',
			[
				'id' => 'enhanced-ui-filelist-cnt'
			]
		);

		$out->addHTML( $html );
	}

}
