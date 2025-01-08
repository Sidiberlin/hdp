<?php

namespace BlueSpice\Bookshelf\LineProcessor;

use BlueSpice\Bookshelf\ILineProcessor;
use BlueSpice\Bookshelf\TreeNode;
use DOMDocument;
use DOMElement;

class Tag extends LineProcessorBase implements ILineProcessor {

	/**
	 * Process the given line
	 *
	 * @param string $line
	 * @return TreeNode
	 */
	public function process( $line ) {
		$result = new TreeNode();
		$result['type'] = 'tag';
		$result['title'] = $line;
		$result['display-title'] = $line;
		$result['bookshelf']['type'] = 'tag';
		$result['bookshelf']['text'] = $line;
		$result['bookshelf']['arguments'] = [];

		$oDOM = new DOMDocument();
		$oDOM->loadXML( '<xml>' . $line . '</xml>' );
		// This is generally dangerous. But in current context it should be okay
		$oTag = $this->getFirstElement( $oDOM->documentElement );
		if ( $oTag === null ) {
			return $result;
		}
		$aAttributes = [];
		foreach ( $oTag->attributes as $sAttributName => $sAttributValue ) {
			// Filter out standard parameters 'text'
			if ( strtolower( $sAttributName ) === 'text' ) {
				continue;
			}
			$aAttributes[$sAttributName] = $sAttributValue->value;
		}
		$result['title'] = $oTag->getAttribute( 'text' );
		$result['display-title'] = $oTag->getAttribute( 'text' );
		$result['bookshelf']['text'] = $oTag->getAttribute( 'text' );
		$result['bookshelf']['arguments'] = $aAttributes;

		return $result;
	}

	/**
	 *
	 * @param DOMElement $el
	 * @return DOMElement|null
	 */
	private function getFirstElement( $el ) {
		foreach ( $el->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement ) {
				return $childNode;
			}
			return $this->getFirstElement( $childNode );
		}
		return null;
	}

	/**
	 * @param string $line
	 * @return bool
	 */
	public function applies( $line ) {
		if ( preg_match( '#^<.*?>$#', $line ) === 1 ) {
			return true;
		}
		return false;
	}
}
