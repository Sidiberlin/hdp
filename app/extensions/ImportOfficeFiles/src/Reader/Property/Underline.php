<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Property;

class Underline extends TagPropertyProcessorBase {

	/**
	 * @return string
	 */
	public function getPropertyName(): string {
		return 'w:u';
	}

	/**
	 * @return string
	 */
	public function process(): string {
		$wikiText = "<u>$this->wikiText</u>";
		return $wikiText;
	}
}
