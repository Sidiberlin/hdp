<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Property;

class Shadow extends TagPropertyProcessorBase {

	/**
	 * @inheritDoc
	 */
	public function getPropertyName(): string {
		return 'w:shd';
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
		if ( isset( $this->properties[ $this->getPropertyName() ]['w:fill'] ) ) {
			return [
				'background-color' => $this->properties[$this->getPropertyName()]['w:fill']
			];
		} else {
			return [];
		}
	}
}
