<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Property;

use MediaWiki\Extension\ImportOfficeFiles\ITagPropertyProcessor;
use MediaWiki\Extension\ImportOfficeFiles\Reader\Component\Word2007DocumentData;

abstract class TagPropertyProcessorBase implements ITagPropertyProcessor {

	/**
	 * @var string
	 */
	protected $wikiText = '';

	/**
	 * @var array
	 */
	protected $properties = [];

	/**
	 * @var Word2007DocumentData
	 */
	protected $documentData = [];

	/**
	 * @return ITagPropertyProcessor
	 */
	public static function factory() {
		return new static();
	}

	/**
	 * @param string $wikiText
	 * @return void
	 */
	public function setWikiText( $wikiText ): void {
		$this->wikiText = $wikiText;
	}

	/**
	 * @param array $properties
	 * @return void
	 */
	public function setProperties( $properties ): void {
		$this->properties = $properties;
	}

	/**
	 * @param Word2007DocumentData $documentData
	 * @return void
	 */
	public function setDocumentData( Word2007DocumentData $documentData ): void {
		$this->documentData = $documentData;
	}

	/**
	 * Get array of CSS styles which should be applied to wikitext after wrapping in some tag.
	 * For example, styles to color text.
	 *
	 * @return array
	 */
	public function getWrapperStyles(): array {
		return [];
	}

	/**
	 * @return bool
	 */
	public function skipProcessing(): bool {
		$name = $this->getPropertyName();
		if ( !isset( $this->properties[$name] ) ) {
			return true;
		}
		return false;
	}
}
