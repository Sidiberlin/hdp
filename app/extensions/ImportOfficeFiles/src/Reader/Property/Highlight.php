<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Property;

class Highlight extends TagPropertyProcessorBase {

	/**
	 * @inheritDoc
	 */
	public function getPropertyName(): string {
		return 'w:highlight';
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
		return [
			'background-color' => $this->properties[$this->getPropertyName()]['w:val']
		];
	}
}
