<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Property;

class Color extends TagPropertyProcessorBase {

	/**
	 * @inheritDoc
	 */
	public function getPropertyName(): string {
		return 'w:color';
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
		$color = $this->properties[ $this->getPropertyName() ]['w:val'];
		return [
			'color' => "#$color"
		];
	}
}
