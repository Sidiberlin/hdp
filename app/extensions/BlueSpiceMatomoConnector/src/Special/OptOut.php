<?php

namespace BlueSpice\MatomoConnector\Special;

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;

class OptOut extends SpecialPage {

	public function __construct() {
		parent::__construct( 'OptOut', 'read', false );
	}

	/** @inheritDoc */
	public function execute( $param ) {
		parent::execute( $param );

		$baseUrl = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'bsg' )
			->get( 'MatomoConnectorBaseUrl' );
		if ( !$baseUrl ) {
			return;
		}
		$baseUrl = rtrim( $baseUrl, '/' );

		$output = $this->getOutput();
		$output->setPageTitle(
			$this->msg( 'bs-matomoconnector-special-optoutheading' )->text()
		);
		$output->addHtml(
			$this->msg( 'bs-matomoconnector-special-privacynote' )->text()
		);

		[ $lang ] = explode(
			'-',
			$this->getContext()->getLanguage()->getCode()
		);

		$html = Html::element( 'iframe', [
			'frameborder' => 'no',
			'width' => '100%',
			'height' => '100%',
			'src' => "$baseUrl/index.php?module=CoreAdminHome&action=optOut&language=$lang"
		] );
		$output->addHTML( $html );
	}
}
