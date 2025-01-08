<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag\Paragraph;

use MediaWiki\Extension\ImportOfficeFiles\Reader\Tag\Paragraph;

class ListItem extends Paragraph {

	/**
	 * ID of the latest processed list.
	 *
	 * @var int|null
	 */
	private static $lastProcessedList = null;

	/**
	 * @param \DOMNode $node
	 * @param array $properties
	 * @return string
	 */
	public function processListItem( \DOMNode $node, array $properties ): string {
		$listId = $properties['w:numPr']['w:numId']['w:val'];
		$level = $properties['w:numPr']['w:ilvl']['w:val'];

		$wikiText = '';
		if ( self::$lastProcessedList === null || self::$lastProcessedList !== $listId ) {
			// This is new list
			$wikiText .= "\n\n";
		} else {
			$wikiText .= "\n";
		}

		$numberingData = $this->documentData->getNumbering();

		$listDefinitions = $numberingData->getListDefinitions();
		$listMapping = $numberingData->getListMapping();

		$listDefinitionId = $listMapping[$listId];

		$isOrdered = $listDefinitions[$listDefinitionId][$level]['ordered'];
		if ( $isOrdered ) {
			$itemListSymbol = '#';
		} else {
			$itemListSymbol = '*';
		}

		for ( $i = 0; $i <= $level; $i++ ) {
			$wikiText .= $itemListSymbol;
		}

		$wikiText .= ' ';

		self::$lastProcessedList = $listId;

		return $wikiText;
	}
}
