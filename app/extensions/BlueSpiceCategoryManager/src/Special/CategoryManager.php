<?php

namespace BlueSpice\CategoryManager\Special;

use MediaWiki\Html\Html;
use OOJSPlus\Special\OOJSTreeSpecialPage;

class CategoryManager extends OOJSTreeSpecialPage {

	public function __construct() {
		parent::__construct(
			'BlueSpiceCategoryManager',
			'edit'
		);
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute( $subPage ) {
		$out = $this->getOutput();
		$out->addModules( [ 'ext.bluespice.categorymanager' ] );

		$out->addHTML(
			Html::element( 'div', [
				'id' => 'bs-categorymanager-tree'
			] )
		);
	}

}
