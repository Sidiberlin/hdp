<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag;

use DOMNode;

class Text extends TagProcessorBase {

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	public function process( DOMNode $node ): string {
		$wikiText = $node->textContent;
		return $wikiText;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return 't';
	}

	/**
	 * @return string
	 */
	public function getNamespace(): string {
		return 'w';
	}
}
