<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag;

use DOMNode;

class Hyperlink extends TagProcessorBase {

	/**
	 * @inheritDoc
	 */
	public function process( DOMNode $node ): string {
		$relId = $node->getAttribute( 'r:id' );
		if ( $relId ) {
			$wikitext = $this->processExternalLink( $node, $relId );
		} else {
			$wikitext = $this->processAnchorLink( $node );
		}

		return $wikitext;
	}

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	private function processAnchorLink( DOMNode $node ): string {
		$anchorName = $node->getAttribute( 'w:anchor' );
		$title = $node->textContent;

		// There already should be "anchor" span with necessary id, after processing of bookmarks
		// So now we just need to create internal link to this span

		return "[[#$anchorName|$title]]";
	}

	/**
	 * @param DOMNode $node
	 * @param string $relId
	 * @return string
	 */
	private function processExternalLink( DOMNode $node, string $relId ): string {
		$link = false;
		$relations = $this->documentData->getRels();
		foreach ( $relations as $relation ) {
			if ( $relation['Id'] == $relId ) {
				$link = $relation['Target'];
			}
		}

		$title = $node->textContent;

		if ( $link ) {
			$wikitext = "[$link $title]";
		} else {
			// Broken link
			$wikitext = "";
		}

		return $wikitext;
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return 'hyperlink';
	}

	/**
	 * @inheritDoc
	 */
	public function getNamespace(): string {
		return 'w';
	}

}
