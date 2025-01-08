<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag;

use DOMNode;

class InstrText extends TagProcessorBase {

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	public function process( DOMNode $node ): string {
		// This tag does not contain document text but instructions
		return '';
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return 'instrText';
	}

	/**
	 * @return string
	 */
	public function getNamespace(): string {
		return 'w';
	}
}
