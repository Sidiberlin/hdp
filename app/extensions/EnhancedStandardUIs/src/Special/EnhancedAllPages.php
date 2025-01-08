<?php

namespace MediaWiki\Extension\EnhancedStandardUIs\Special;

use ExtensionRegistry;
use Html;
use OOUI\FieldLayout;
use OOUI\SearchInputWidget;
use SpecialPage;

class EnhancedAllPages extends SpecialPage {

	public function __construct() {
		parent::__construct( 'EnhancedAllPages' );
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->getOutput()->enableOOUI();

		$this->getOutput()->addModules( 'ext.enhancedstandarduis.special.allpages' );
		$modules = ExtensionRegistry::getInstance()->getAttribute(
			'EnhancedStandardUIsAllPagesPluginModules'
		);
		$this->getOutput()->addModules( $modules );

		$html = Html::openElement(
			'div',
			[
				'id' => 'enhanced-ui-allpages-cnt'
			]
		);
		$html .= $this->getSearchWidget();
		$html .= Html::closeElement( 'div' );
		$this->getOutput()->addHTML( $html );
	}

	/**
	 *
	 * @return string
	 */
	private function getSearchWidget() {
		$searchInput = new SearchInputWidget( [
			'infusable' => true,
			'id' => 'enhanced-ui-allpages-filter',
			'icon' => 'search'
		] );
		$search = Html::openElement( 'div', [
			'class' => 'allpages-filter-cnt'
		] );
		$search .= new FieldLayout( $searchInput, [
			'label' => $this->msg( 'enhanced-standard-uis-allpages-filter-pages-label' )->text(),
			'align' => 'left'
		] );
		$search .= Html::closeElement( 'div' );

		return $search;
	}
}
