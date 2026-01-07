<?php

namespace BlueSpice\WikiExplorer\Special;

use MediaWiki\Html\Html;
use OOJSPlus\Special\OOJSGridSpecialPage;

class SpecialWikiExplorer extends OOJSGridSpecialPage {

	public function __construct() {
		parent::__construct( 'WikiExplorer', 'read' );
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute( $subPage ) {
		$this->getOutput()->addModules( 'ext.bluespice.wikiExplorer.special' );

		$this->getOutput()->addHTML( Html::element( 'div', [
			'id' => 'wikiexplorer-loader',
			'style' => 'height: 30px;'
		] ) );
		$this->getOutput()->addHTML( Html::element( 'div', [ 'id' => 'wikiexplorer' ] ) );
	}
}
