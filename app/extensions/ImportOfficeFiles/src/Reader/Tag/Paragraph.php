<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag;

use DOMNode;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Tag\Paragraph\ListItem;

class Paragraph extends TagProcessorBase {

	/**
	 * @inheritDoc
	 */
	protected $wrapHtmlTag = 'p';

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	public function process( DOMNode $node ): string {
		$properties = $this->getProperties( $node );

		if ( strlen( $node->textContent ) === 0 ) {
			return '';
		}

		// If this paragraph is a list part
		if ( isset( $properties['w:numPr'] ) ) {
			$listItemProcessor = new ListItem();
			$listItemProcessor->setDocumentData( $this->documentData );
			$wikiText = $listItemProcessor->processListItem( $node, $properties );
		} else {
			$wikiText = "\n\n";
		}

		$wikiText .= $this->processProperties( $node->textContent, $properties, $this->documentData );
		return $wikiText;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return 'p';
	}

	/**
	 * @return string
	 */
	public function getNamespace(): string {
		return 'w';
	}
}
