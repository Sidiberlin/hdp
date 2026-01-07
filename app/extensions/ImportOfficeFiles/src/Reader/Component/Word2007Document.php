<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Component;

use DOMDocument;
use DOMNode;
use DOMNodeList;
use MediaWiki\Extension\ImportOfficeFiles\Reader\DocumentPreprocessor;
use MediaWiki\Extension\ImportOfficeFiles\Reader\DocumentSplitter;
use MediaWiki\Extension\ImportOfficeFiles\Segment;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;

class Word2007Document {

	/**
	 * @var Word2007DocumentData
	 */
	private $documentData;

	/**
	 * @var bool
	 */
	private $verbose;

	/**
	 * @param string $path
	 * @param Word2007DocumentData $documentData
	 * @param bool $verbose
	 * @return array
	 */
	public function execute( $path, Word2007DocumentData $documentData, bool $verbose = false ): array {
		$filePath = $path . '/document/word/document.xml';
		if ( !file_exists( $filePath ) ) {
			return [];
		}

		$this->verbose = $verbose;

		$status = wfMkdirParents( "$path/raw", null, get_class( $this ) );

		$this->documentData = $documentData;

		$dom = new DOMDocument();
		$dom->load( $filePath );

		$documentPreprocessor = new DocumentPreprocessor();
		$documentPreprocessor->execute( $dom );

		// split document
		$documentSplitter = new DocumentSplitter();
		$splitDomList = $documentSplitter->split( $path, $dom, $this->documentData->getStyles() );

		if ( $this->verbose ) {
			echo "\nReading document ...\n";
		}
		$counter = 1;
		$segments = [];
		foreach ( $splitDomList as $splitDomItem ) {
			if ( $this->verbose ) {
				echo "\n" . $splitDomItem['file'] . "\n";
			}

			$splitDom = new DOMDocument();
			$splitDom->load( $splitDomItem['file'] );
			$splitDomElements = $splitDom->getElementsByTagName( 'body' );
			$this->readXML( $splitDomElements );

			$splitDomText = $this->convertSegment( $splitDom );
			$heading = $this->getHeadingText( $splitDomItem['level'], $splitDomText );

			file_put_contents( "$path/raw/part-$counter.raw", $splitDomText );
			$splitDom->save( "$path/raw/part-$counter.dom" );

			$segments[] = new Segment(
				$splitDomItem['level'],
				$heading,
				"$path/raw/part-$counter.raw"
			);
			$counter++;
		}
		if ( $this->verbose ) {
			echo "\n\nReading document done\n";
		}
		return [
			'segments' => $segments
		];
	}

	/**
	 * @param DOMNodeList $nodes
	 */
	private function readXML( $nodes ) {
		$extensionRegistry = ExtensionRegistry::getInstance();
		$registry = $extensionRegistry->getAttribute(
			'ImportOfficeFilesWord2007TagProcessorRegistry'
		);
		// TODO: inject MediaWikiServices
		$services = MediaWikiServices::getInstance();
		$config = $services->getMainConfig();
		$pipeline = $config->get( 'ImportOfficeFilesWord2007TagProcessorPipeline' );

		$objectFactory = $services->getObjectFactory();

		foreach ( $pipeline as $processorName ) {
			if ( !isset( $registry[$processorName] ) ) {
				continue;
			}
			$processor = $objectFactory->createObject(
				$registry[$processorName]
			);
			$processor->setDocumentData( $this->documentData );

			$liveList = $processor->getProcessableElementsFromDocument( $nodes->item( 0 ) );
			$nonLiveList = [];
			foreach ( $liveList as $liveNode ) {
				$nonLiveList[] = $liveNode;
			}
			foreach ( $nonLiveList as $nonLiveNode ) {
				if ( $this->verbose ) {
					echo ".";
				}
				$wikiTextReplacement = $processor->process( $nonLiveNode );
				$cDataSecton = $nonLiveNode->ownerDocument->createCdataSection( $wikiTextReplacement );
				$replacementNode = $nonLiveNode->ownerDocument->createElement( 'wikitext' );
				$replacementNode->appendChild( $cDataSecton );
				$nonLiveNode->parentNode->replaceChild( $replacementNode, $nonLiveNode );
			}
		}
	}

	/**
	 * @param DOMDocument $dom
	 * @return string
	 */
	private function convertSegment( $dom ): string {
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		$wikiText =	$this->getWikiTextNodeContent( $body );
		return $wikiText;
	}

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	private function getWikiTextNodeContent( $node ): string {
		$nodeText = '';
		if ( !$node->childNodes ) {
			return $nodeText;
		}
		$wikiTextNodes = $node->getElementsByTagName( 'wikitext' );
		foreach ( $wikiTextNodes as $wikiTextNode ) {
			$nodeText .= $wikiTextNode->textContent;
		}
		return $nodeText;
	}

	/**
	 * @param int $level
	 * @param string $wikiText
	 * @return string
	 */
	private function getHeadingText( $level, $wikiText ): string {
		$heading = '';
		$markup = '';
		for ( $wtLevel = 0; $wtLevel < $level; $wtLevel++ ) {
			$markup .= '=';
		}
		$matches = [];
		preg_match( "/$markup(.*?)$markup/m", $wikiText, $matches );
		if ( count( $matches ) > 0 ) {
			$heading = $matches[1];
			$heading = preg_replace( '#<(.*?)>#m', '', $heading );
		}
		return $heading;
	}
}
