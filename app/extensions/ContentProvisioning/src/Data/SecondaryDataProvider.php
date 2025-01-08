<?php

namespace MediaWiki\Extension\ContentProvisioning\Data;

use MWStake\MediaWiki\Component\DataStore\ISecondaryDataProvider;
use RequestContext;
use Title;

class SecondaryDataProvider implements ISecondaryDataProvider {

	/**
	 * @var RequestContext
	 */
	private $context;

	/**
	 *
	 */
	public function __construct() {
		$this->context = RequestContext::getMain();
	}

	/**
	 * @inheritDoc
	 */
	public function extend( $dataSets ) {
		foreach ( $dataSets as &$dataSet ) {
			$pagePrefixedText = $dataSet->get( Record::PAGE_PREFIXED_TEXT );

			$title = Title::newFromText( $pagePrefixedText );
			if ( $title instanceof Title ) {
				$dataSet->set( Record::PAGE_LINK, $title->getLocalURL() );
			}
		}

		return $dataSets;
	}
}
