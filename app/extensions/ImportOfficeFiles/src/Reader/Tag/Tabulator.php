<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag;

use DOMNode;

class Tabulator extends TagProcessorBase {

	/**
	 * @inheritDoc
	 */
	public function process( DOMNode $node ): string {
		return "\t";
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return 'tab';
	}

	/**
	 * @inheritDoc
	 */
	public function getNamespace(): string {
		return 'w';
	}
}
