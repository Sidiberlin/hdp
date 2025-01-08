<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Component;

use DOMDocument;
use DOMXPath;

class Word2007Numbering {

	/**
	 * @var array
	 * @see Word2007Numbering::readListDefinitions
	 */
	private $listDefinitions;

	/**
	 * @var array
	 * @see Word2007Numbering::readListMapping
	 */
	private $listMapping;

	/**
	 * @return array
	 */
	public function getListDefinitions(): array {
		return $this->listDefinitions;
	}

	/**
	 * @return array
	 */
	public function getListMapping(): array {
		return $this->listMapping;
	}

	/**
	 * @param array $listDefinitions
	 * @param array $listMapping
	 */
	public function __construct( array $listDefinitions = [], array $listMapping = [] ) {
		$this->listDefinitions = $listDefinitions;
		$this->listMapping = $listMapping;
	}

	/**
	 * Parses Word numbering XML file to get data about lists, used in the Word document
	 * Sets {@link Word2007Numbering::$listMapping} and {@link Word2007Numbering::$listDefinitions} properties
	 *
	 * @param string $path Path to root directory of extracted Word document
	 */
	public function parseData( $path ): void {
		$filePath = $path . '/document/word/numbering.xml';
		if ( !file_exists( $filePath ) ) {
			return;
		}

		$dom = new DOMDocument();
		$dom->load( $filePath );
		$xpath = new DOMXPath( $dom );

		$this->listDefinitions = $this->readListDefinitions( $xpath );
		$this->listMapping = $this->readListMapping( $xpath );
	}

	/**
	 * Gets abstract list definitions.
	 * Result is an array where key is ID of abstract list definition, and value array with list configuration.
	 *
	 * @param DOMXPath $xpath XPath, linked to XML document with numbering data
	 * @return array Array with such structure:
	 * [
	 * 		<abstractListId1> => [
	 * 			<listLevelId1> => [
	 * 				'ordered' => <isOrdered1>
	 * 			],
	 * 			<listLevelId2> => [
	 * 				'ordered' => <isOrdered2>
	 * 			],
	 * 			...
	 * 		],
	 * 		<abstractListId1> => [
	 * 		...
	 * 		],
	 * 		...
	 * ]
	 */
	private function readListDefinitions( DOMXPath $xpath ): array {
		// Nodes which contain abstract list definitions
		$listDefinitionNodes = $xpath->query( '//w:abstractNum' );

		// Contains configuration of each abstract list definition.
		// Used further to map specific list instance to its configuration
		$listDefinitions = [];
		foreach ( $listDefinitionNodes as $listDefinitionNode ) {
			$listId = $listDefinitionNode->getAttribute( 'w:abstractNumId' );

			// As soon as in MediaWiki list can be only ordered or unordered
			// We need to check the levels of Word list for their format
			$levelDefinitionNodes = $listDefinitionNode->getElementsByTagName( 'lvl' );
			foreach ( $levelDefinitionNodes as $levelDefinitionNode ) {
				$levelConfiguration = [];

				$format = $levelDefinitionNode
					->getElementsByTagName( 'numFmt' )
					->item( 0 )
					->getAttribute( 'w:val' );

				if ( $format == 'bullet' ) {
					$levelConfiguration['ordered'] = false;
				} else {
					// All other formats in Word except "bullet" are ordered.

					// We don't care which of them exactly is used,
					// because in MediaWiki there is only one format for ordered lists
					$levelConfiguration['ordered'] = true;
				}

				$levelId = $levelDefinitionNode->getAttribute( 'w:ilvl' );
				$listDefinitions[$listId][$levelId] = $levelConfiguration;
			}
		}
		return $listDefinitions;
	}

	/**
	 * Gets mapping of specific list instances, appearing in Word document, to abstract list definitions.
	 *
	 * @param DOMXPath $xpath XPath, linked to XML document with numbering data
	 * @return array Array with such structure:
	 * [
	 * 		<listId1> => <abstractDefinitionId1>,
	 * 		<listId2> => <abstractDefinitionId2>,
	 * 		...
	 * ]
	 */
	private function readListMapping( DOMXPath $xpath ): array {
		// Nodes, containing "list -> definition" mappings
		$listMappingNodes = $xpath->query( '//w:num' );

		$listMapping = [];
		foreach ( $listMappingNodes as $listMappingNode ) {
			$listId = $listMappingNode->getAttribute( 'w:numId' );
			$abstractDefinitionId = $listMappingNode
				->getElementsByTagName( 'abstractNumId' )
				->item( 0 )
				->getAttribute( 'w:val' );

			$listMapping[$listId] = $abstractDefinitionId;
		}
		return $listMapping;
	}
}
