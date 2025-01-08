<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

class RemoveHeading {

	/**
	 * @var string
	 */
	private $whiteSpaceRegEx = '[:#<>\s\/\t\n\[\]\{\}\|]';

	/**
	 * @param string $level
	 * @param string $heading
	 * @param string $wikiText
	 * @return string
	 */
	public function execute( $level, $heading, $wikiText ): string {
		$sanitizer = new SanitizeHeading();
		$sanitizedWikiText = $sanitizer->execute( $level, $wikiText );

		$titleParts = explode( ':', $heading );
		$heading = array_pop( $titleParts );

		$headingParts = explode( '/', $heading );
		$heading = array_pop( $headingParts );

		// Prepare $heading text for regex
		$quotedHeading = preg_quote( $heading );
		/* In $heading invalid characters are replaced by ' '. To match them in
		 * wikitext the ' ' is replaced by a regex
		 */
		$quotedHeading = str_replace( ' ', $this->whiteSpaceRegEx, $quotedHeading );
		$regEx = "/^={" . $level . "}\s*" . $quotedHeading . "\s*={" . $level . "}/m";
		$newWikiText = preg_replace( $regEx, '', $sanitizedWikiText );

		if ( !$newWikiText ) {
			return $wikiText;
		}

		return $newWikiText;
	}
}
