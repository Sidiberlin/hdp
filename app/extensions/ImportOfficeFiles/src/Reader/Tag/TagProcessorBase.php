<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag;

use DOMElement;
use DOMNode;
use ExtensionRegistry;
use MediaWiki\Extension\ImportOfficeFiles\ITagProcessor;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Component\Word2007DocumentData;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Property\TagPropertyProcessorBase;
use MediaWiki\MediaWikiServices;

abstract class TagProcessorBase implements ITagProcessor {

	/**
	 * @var Word2007DocumentData
	 */
	protected $documentData = null;

	/**
	 * HTML tag to wrap XML tag's content into.
	 * Could be 'span' or 'div'
	 *
	 * @var string
	 */
	protected $wrapHtmlTag = '';

	/**
	 * @var array
	 */
	protected $properties = [];

	/**
	 * @return ITagProcessor
	 */
	public static function factory() {
		return new static();
	}

	/**
	 * @param Word2007DocumentData $documentData
	 */
	public function setDocumentData( Word2007DocumentData $documentData ) {
		$this->documentData = $documentData;
	}

	/**
	 * @param DOMNode $node
	 * @return DOMNode[]
	 */
	public function getProcessableElementsFromDocument( $node ): array {
		$processableNodes = $node->getElementsByTagName( $this->getName() );
		$nodes = [];
		foreach ( $processableNodes as $processableNode ) {
			$nodes[] = $processableNode;
		}
		return $nodes;
	}

	/**
	 * @param string $parentNodeName
	 * @param string $childNodeName
	 * @return bool
	 */
	private function isPropertyNode( $parentNodeName, $childNodeName ): bool {
		if ( $childNodeName === $parentNodeName . 'Pr' ) {
			return true;
		}
		return false;
	}

	/**
	 * @param DOMNode $node
	 * @return array
	 */
	protected function getProperties( $node ): array {
		$staticChildNodes = [];
		foreach ( $node->childNodes as $childNode ) {
			$staticChildNodes[] = $childNode;
		}
		foreach ( $staticChildNodes as $staticChildNode ) {
			if ( $this->isPropertyNode( $node->nodeName, $staticChildNode->nodeName ) ) {
				return $this->getPropertyNodeArray( $staticChildNode );
			}
		}
		return [];
	}

	/**
	 * @param DOMNode $node
	 * @return array
	 */
	private function getPropertyNodeArray( $node ): array {
		$nodeArray = [];
		$childNodes = $node->childNodes;
		foreach ( $childNodes as $childNode ) {
			$name = str_replace( $node->parentNode->nodeName, '', $childNode->nodeName );
			if ( $childNode instanceof DOMElement && $childNode->hasAttributes() ) {
				// Collect property attributes
				foreach ( $childNode->attributes as $attr ) {
					$nodeArray[$name][$attr->nodeName] = $attr->nodeValue;
				}
			} elseif ( count( $childNode->childNodes ) > 0 ) {
				$nodeArray[$name] = $this->getPropertyNodeArray( $childNode );
			} else {
				// Toggle property
				$nodeArray[$name] = 'true';
			}
		}
		return $nodeArray;
	}

	/**
	 * @param string $wikiText
	 * @param array $properties
	 * @param Word2007DocumentData $documentData
	 * @return string
	 */
	protected function processProperties( $wikiText, $properties, Word2007DocumentData $documentData ): string {
		if ( empty( $properties ) ) {
			return $wikiText;
		}

		if ( $wikiText === '' ) {
			return $wikiText;
		}

		$extensionRegistry = ExtensionRegistry::getInstance();
		$registry = $extensionRegistry->getAttribute(
			'ImportOfficeFilesWord2007TagPropertyProcessorRegistry'
		);
		// TODO: inject MediaWikiServices
		$services = MediaWikiServices::getInstance();
		$config = $services->getMainConfig();
		$pipeline = $config->get( 'ImportOfficeFilesWord2007TagPropertyProcessorPipeline' );

		$htmlWrapper = null;
		if ( $this->wrapHtmlTag ) {
			$htmlWrapper = new HtmlWrapper( $this->wrapHtmlTag );
		}

		foreach ( $pipeline as $processorName ) {
			if ( !isset( $registry[$processorName] ) ) {
				continue;
			}

			$objectFactory = MediaWikiServices::getInstance()->getObjectFactory();
			/** @var TagPropertyProcessorBase $processor */
			$processor = $objectFactory->createObject(
				$registry[$processorName]
			);
			$processor->setWikiText( $wikiText );
			$processor->setProperties( $properties );
			$processor->setDocumentData( $documentData );
			if ( !$processor->skipProcessing() ) {
				$wikiText = $processor->process();

				$styles = $processor->getWrapperStyles();
				if ( $styles ) {
					if ( $htmlWrapper !== null ) {
						$htmlWrapper->setStyles( $styles );
					} else {
						// Cases, when there are some styles to apply, but there is no wrapper - should not be
						// Probably some log notice is needed here
					}
				}
			}
		}

		if ( $htmlWrapper !== null && $wikiText !== '' ) {
			$wikiText = $htmlWrapper->wrap( $wikiText );
		}

		return $wikiText;
	}

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	protected function getWikiTextNodeContent( $node ): string {
		$nodeText = '';
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'wikitext' ) {
				$nodeText .= $childNode->textContent;
			}
		}
		return $nodeText;
	}
}
