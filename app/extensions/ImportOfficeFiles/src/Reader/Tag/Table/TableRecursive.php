<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag\Table;

use DOMNode;
use DOMXPath;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Tag\Table;

class TableRecursive extends Table {

	/**
	 * @param DOMNode $node
	 * @return DOMNode[]
	 */
	public function getProcessableElementsFromDocument( $node ): array {
		$xpath = new DOMXPath( $node->ownerDocument );
		$nodePath = $node->getNodePath();
		$xpath->registerNamespace( 'w', 'w' );
		$tables = $xpath->query( $nodePath . '/w:tbl' );

		$nodes = [];
		foreach ( $tables as $table ) {
			$nodes[] = $table;
		}
		return $nodes;
	}

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	protected function replaceTable( $node ): string {
		$replacementText = "\n";
		$replacementText .= '{| class="wikitable"';
		$replacementText .= "\n";
		$replacementText .= $this->getWikiTextNodeContent( $node );
		$replacementText .= "|}";

		return $replacementText;
	}
}
