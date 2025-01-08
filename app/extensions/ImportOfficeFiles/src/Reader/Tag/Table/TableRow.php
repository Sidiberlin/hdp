<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag\Table;

use DOMNode;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Tag\TagProcessorBase;

class TableRow extends TagProcessorBase {

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	public function process( DOMNode $node ): string {
		$nodeText = $this->getWikiTextNodeContent( $node );
		return "|-\n$nodeText";
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return 'tr';
	}

	/**
	 * @return string
	 */
	public function getNamespace(): string {
		return 'w';
	}
}
