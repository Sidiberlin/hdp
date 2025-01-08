<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag\Table;

use DOMNode;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Tag\TagProcessorBase;

class TableCell extends TagProcessorBase {

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	public function process( DOMNode $node ): string {
		$nodeText = $this->getWikiTextNodeContent( $node );
		return "| $nodeText\n";
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return 'tc';
	}

	/**
	 * @return string
	 */
	public function getNamespace(): string {
		return 'w';
	}
}
