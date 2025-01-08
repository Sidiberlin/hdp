<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Property;

class Bold extends TagPropertyProcessorBase {

	/**
	 * @return string
	 */
	public function getPropertyName(): string {
		return 'w:b';
	}

	/**
	 * @return string
	 */
	public function process(): string {
		$start = '';
		$end = '';
		if ( ( strpos( $this->wikiText, " " ) === 0 )
			|| ( strpos( $this->wikiText, "\t" ) === 0 ) ) {
			$start = ' ';
		}
		if ( ( strpos( $this->wikiText, " " ) === strlen( $this->wikiText ) - 1 )
			|| ( strpos( $this->wikiText, "\t" ) === strlen( $this->wikiText ) - 1 ) ) {
			$end = ' ';
		}
		$text = trim( $this->wikiText );
		$wikiText = "$start###PRESERVEBOLD###$text###PRESERVEBOLD###$end";
		return $wikiText;
	}
}
