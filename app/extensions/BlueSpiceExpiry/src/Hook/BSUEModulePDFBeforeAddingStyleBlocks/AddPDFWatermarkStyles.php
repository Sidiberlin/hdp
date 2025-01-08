<?php

namespace BlueSpice\Expiry\Hook\BSUEModulePDFBeforeAddingStyleBlocks;

use BlueSpice\UEModulePDF\Hook\BSUEModulePDFBeforeAddingStyleBlocks;
use RequestContext;

class AddPDFWatermarkStyles extends BSUEModulePDFBeforeAddingStyleBlocks {

	protected function skipProcessing() {
		if ( !$this->getConfig( 'ExpiryEnablePDFWatermark' ) ) {
			return true;
		}
		return false;
	}

	protected function doProcess() {
		$userLanguageSetting = $this->getServices()->getUserOptionsLookup()
			->getOption( RequestContext::getMain()->getUser(), 'language' );
		list( $lang ) = explode( '-', $userLanguageSetting );
		$img = "{$GLOBALS['IP']}/extensions/BlueSpiceExpiry/resources/images/bg-expired-$lang.png";
		if ( !file_exists( $img ) ) {
			$lang = 'en';
			$img = "{$GLOBALS['IP']}/extensions/BlueSpiceExpiry/resources/images/bg-expired-$lang.png";
		}
		$this->template['resources']['IMAGE']["bg-expired-$lang.png"] = $img;

		$this->styleBlocks[ 'Expiry' ] = <<<HEREDOC
.bs-page-content.expired {
	background-image: url('images/bg-expired-$lang.png');
	background-repeat: repeat;
	background-position: top left;
	background-size: auto 6cm;
	min-height: 250px;
}
HEREDOC;
		return true;
	}

}
