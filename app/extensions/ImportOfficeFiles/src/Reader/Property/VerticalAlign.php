<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Property;

class VerticalAlign extends TagPropertyProcessorBase {

	/**
	 * @inheritDoc
	 */
	public function getPropertyName(): string {
		return 'w:vertAlign';
	}

	/**
	 * @inheritDoc
	 */
	public function process(): string {
		if ( $this->properties[ $this->getPropertyName() ]['w:val'] === 'superscript' ) {
			return '<sup>' . $this->wikiText . '</sup>';
		}
		if ( $this->properties[ $this->getPropertyName() ]['w:val'] === 'subscript' ) {
			return '<sub>' . $this->wikiText . '</sub>';
		}

		return $this->wikiText;
	}
}
