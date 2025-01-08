<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Property;

class StrikeThrough extends TagPropertyProcessorBase {

	/**
	 * @return string
	 */
	public function getPropertyName(): string {
		return 'w:strike';
	}

	/**
	 * @return string
	 */
	public function process(): string {
		$wikiText = "<s>$this->wikiText</s>";
		return $wikiText;
	}
}
