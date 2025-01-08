<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

use MediaWiki\Extension\ImportOfficeFiles\Reader\Component\Word2007DocumentData;

interface ITagPropertyProcessor {

	/**
	 * @return string
	 */
	public function process(): string;

	/**
	 * $wikitext is the string which properties should be applied to
	 * @param string $wikiText
	 * @return void
	 */
	public function setWikiText( $wikiText ): void;

	/**
	 * $properties contian all values in e.g. <w:pPr>
	 * @param array $properties
	 * @return void
	 */
	public function setProperties( $properties ): void;

	/**
	 * $documentData contains document data like styles and rels
	 * @param Word2007DocumentData $documentData
	 * @return void
	 */
	public function setDocumentData( Word2007DocumentData $documentData ): void;

	/**
	 * @return string
	 */
	public function getPropertyName(): string;

	/**
	 * @return bool
	 */
	public function skipProcessing(): bool;
}
