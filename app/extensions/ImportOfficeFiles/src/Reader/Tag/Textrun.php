<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag;

use DOMNode;

class Textrun extends TagProcessorBase {

	/**
	 * @inheritDoc
	 */
	protected $wrapHtmlTag = 'span';

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	public function process( DOMNode $node ): string {
		$properties = $this->getProperties( $node );
		if ( strlen( $node->textContent ) === 0 ) {
			return '';
		}
		// TODO: no double linebreak for lists
		$wikiText = $this->processProperties(
			$node->textContent,
			$properties,
			$this->documentData
		);
		return $wikiText;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return 'r';
	}

	/**
	 * @return string
	 */
	public function getNamespace(): string {
		return 'w';
	}
}
