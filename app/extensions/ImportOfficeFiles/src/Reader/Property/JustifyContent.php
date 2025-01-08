<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Property;

class JustifyContent extends TagPropertyProcessorBase {

	/**
	 * @return string
	 */
	public function getPropertyName(): string {
		return 'w:jc';
	}

	/**
	 * @inheritDoc
	 */
	public function process(): string {
		return $this->wikiText;
	}

	/**
	 * @inheritDoc
	 */
	public function getWrapperStyles(): array {
		$justify = $this->properties[ $this->getPropertyName() ]['w:val'];
		return [
			'width' => '100%',
			'text-align' => $justify
		];
	}
}
