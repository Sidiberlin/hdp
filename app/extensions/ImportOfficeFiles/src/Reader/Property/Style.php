<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Property;

use MediaWiki\Extension\ImportOfficeFiles\Reader\Component\Word2007DocumentData;

class Style extends TagPropertyProcessorBase {

	/**
	 * @var array
	 */
	private $openingMarkupMap = [
		'Title' => '{{DISPLAYTITLE:',
		'Subtitle' => '=',
		'Heading 1' => '==',
		'Heading 2' => '===',
		'Heading 3' => '====',
		'Heading 4' => '=====',
		'Heading 5' => '======'

	];

	/**
	 * @var array
	 */
	private $closeingMarkupMap = [
		'Title' => '}}',
		'Subtitle' => '=',
		'Heading 1' => '==',
		'Heading 2' => '===',
		'Heading 3' => '====',
		'Heading 4' => '=====',
		'Heading 5' => '======'

	];

	/**
	 * @return string
	 */
	public function getPropertyName(): string {
		return 'Style';
	}

	/**
	 * @return string
	 */
	public function process(): string {
		$name = $this->getPropertyName();
		if ( !isset( $this->properties[$name] ) ) {
			return $this->wikiText;
		}
		$styleId = $this->getBaseStyleId( $this->properties[$name], $this->documentData );
		$styleId = ucfirst( $styleId );
		if ( $styleId !== '' ) {
			$processedWikiText = $this->getOpeningMarkup( $styleId );
		}
		$processedWikiText .= $this->wikiText;
		if ( $styleId !== '' ) {
			$processedWikiText .= $this->getClosingMarkup( $styleId );
		}
		return $processedWikiText;
	}

	/**
	 * @param array $styleId
	 * @param Word2007DocumentData $documentData
	 * @return string
	 */
	protected function getBaseStyleId( $styleId, Word2007DocumentData $documentData ): string {
		if ( !$documentData->getStyles() || !isset( $styleId['w:val'] ) ) {
			return '';
		}
		foreach ( $documentData->getStyles() as $style ) {
			if ( isset( $style['id'] ) && $style['id'] === $styleId['w:val'] ) {
				$styleId = $style['name'];
				break;
			}
		}
		return $styleId;
	}

	/**
	 * @param string $styleId
	 * @return string
	 */
	private function getOpeningMarkup( $styleId ): string {
		$markup = '';
		if ( isset( $this->openingMarkupMap[$styleId] ) ) {
			$markup = $this->openingMarkupMap[$styleId];
		}
		return $markup;
	}

	/**
	 * @param string $styleId
	 * @return string
	 */
	private function getClosingMarkup( $styleId ): string {
		$markup = '';
		if ( isset( $this->closeingMarkupMap[$styleId] ) ) {
			$markup = $this->closeingMarkupMap[$styleId];
		}
		return $markup;
	}
}
