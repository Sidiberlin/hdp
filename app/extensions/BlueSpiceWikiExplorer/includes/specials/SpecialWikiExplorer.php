<?php

/**
 * Renders the WikiExplorer special page.
 *
 * Part of BlueSpice MediaWiki
 *
 * @author     Sebastian Ulbricht <sebastian.ulbricht@dragon-network.hk>
 * @version    $Id: SpecialExtendedStatistics.class.php 7886 2012-12-21 00:14:23Z mglaser $
 * @package    BlueSpice_Extensions
 * @subpackage WikiExplorer
 * @copyright  Copyright (C) 2016 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GPL-3.0-only
 * @filesource
 */

/**
 * WikiExplorer special page that renders the list itself
 * @package BlueSpice_Extensions
 * @subpackage WikiExplorer
 */
class SpecialWikiExplorer extends SpecialPage {

	/**
	 * Constructor of SpecialExtendedStatistics
	 */
	public function __construct() {
		parent::__construct( 'WikiExplorer' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->getOutput()->enableOOUI();

		$this->getOutput()->addModules( 'ext.bluespice.wikiExplorer.special' );

		$this->getOutput()->addHTML( Html::element( 'div', [
			'id' => 'wikiexplorer-loader',
			'style' => 'height: 30px;'
		] ) );
		$this->getOutput()->addHTML( Html::element( 'div', [ 'id' => 'wikiexplorer' ] ) );
	}
}
