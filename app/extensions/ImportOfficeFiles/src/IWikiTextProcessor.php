<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

interface IWikiTextProcessor {

	/**
	 * @param string $wikiText
	 * @return string
	 */
	public function process( string $wikiText ): string;
}
