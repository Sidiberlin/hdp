<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag;

use DOMNode;

class LineBreak extends TagProcessorBase {

	/**
	 * @inheritDoc
	 */
	public function process( DOMNode $node ): string {
		return "<br />";
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return 'br';
	}

	/**
	 * @inheritDoc
	 */
	public function getNamespace(): string {
		return 'w';
	}
}
