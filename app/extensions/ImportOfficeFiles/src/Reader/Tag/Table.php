<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag;

use DOMNode;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Tag\Table\TableCell;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Tag\Table\TableRecursive;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Tag\Table\TableRow;

class Table extends TagProcessorBase {

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	public function process( DOMNode $node ): string {
		$wikitext = $this->processTable( $node );
		$wikitext .= "\n";

		return $wikitext;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return 'tbl';
	}

	/**
	 * @return string
	 */
	public function getNamespace(): string {
		return 'w';
	}

	/**
	 * @param DOMNode $node
	 * @return DOMNode[]
	 */
	public function getProcessableElementsFromDocument( $node ): array {
		$tables = $node->getElementsByTagName( 'tbl' );
		$nodes = [];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName !== 'w:tbl' ) {
				continue;
			}
			$nodes[] = $childNode;
		}
		return $nodes;
	}

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	private function processTable( $node ): string {
		$node = $this->replaceTableCells( $node );
		$node = $this->replaceTableRows( $node );
		$wikiText = $this->replaceTable( $node );

		return $wikiText;
	}

	/**
	 * @param DOMNode $node
	 * @return DOMNode
	 */
	private function replaceTableCells( $node ): DOMNode {
		$tcProcessor = new TableCell();

		$liveList = $node->getElementsByTagName( 'tc' );
		$nonLiveList = [];
		foreach ( $liveList as $liveNode ) {
			$nonLiveList[] = $liveNode;
		}

		foreach ( $nonLiveList as $nonLiveNode ) {
			$tables = $nonLiveNode->getElementsByTagName( 'tbl' );
			$nonLiveTables = [];
			foreach ( $tables as $table ) {
				$nonLiveTables[] = $table;
			}

			if ( count( $nonLiveTables ) > 0 ) {
				$tableProcessor = new TableRecursive();
				foreach ( $nonLiveTables as $nonLiveTable ) {
					$replacementText = $tableProcessor->process( $nonLiveTable );
					$replacementNode = $nonLiveNode->ownerDocument->createElement( 'wikitext', $replacementText );
					$nonLiveTable->parentNode->replaceChild( $replacementNode, $nonLiveTable );
				}
			}
		}

		// Refresh nonLiveList
		$liveList = $node->getElementsByTagName( 'tc' );
		$nonLiveList = [];
		foreach ( $liveList as $liveNode ) {
			$nonLiveList[] = $liveNode;
		}

		foreach ( $nonLiveList as $nonLiveNode ) {
			$replacementText = $tcProcessor->process( $nonLiveNode );
			$replacementNode = $nonLiveNode->ownerDocument->createElement( 'wikitext', $replacementText );
			$nonLiveNode->parentNode->replaceChild( $replacementNode, $nonLiveNode );
		}

		return $node;
	}

	/**
	 * @param DOMNode $node
	 * @return DOMNode
	 */
	private function replaceTableRows( $node ): DOMNode {
		$trProcessor = new TableRow();

		$liveList = $node->getElementsByTagName( 'tr' );
		$nonLiveList = [];
		foreach ( $liveList as $liveNode ) {
			$nonLiveList[] = $liveNode;
		}

		foreach ( $nonLiveList as $nonLiveNode ) {
			$replacementText = $trProcessor->process( $nonLiveNode );
			$replacementNode = $nonLiveNode->ownerDocument->createElement( 'wikitext', $replacementText );
			$nonLiveNode->parentNode->replaceChild( $replacementNode, $nonLiveNode );
		}

		return $node;
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
			$replacementText .= "\n";

		return $replacementText;
	}
}
