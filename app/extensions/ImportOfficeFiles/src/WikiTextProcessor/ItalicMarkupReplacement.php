<?php

namespace MediaWiki\Extension\ImportOfficeFiles\WikiTextProcessor;

use MediaWiki\Extension\ImportOfficeFiles\IWikiTextProcessor;

class ItalicMarkupReplacement implements IWikiTextProcessor {

	/**
	 * @param string $wikiText
	 * @return string
	 */
	public function process( string $wikiText ): string {
		$wikiText = str_replace( '###PRESERVEITALIC######PRESERVEITALIC###', '', $wikiText );

		$matches = explode( '###PRESERVEITALIC###', $wikiText );
		if ( !$matches || count( $matches ) <= 1 ) {
			return $wikiText;
		}

		$matches = array_filter( $matches, static function ( $item ) {
			return !empty( $item );
		} );

		$wikiTextLength = strlen( $wikiText );
		$placeholderLength = strlen( '###PRESERVEITALIC###' );
		if ( strpos( $wikiText, '###PRESERVEITALIC###', $wikiTextLength - $placeholderLength ) ) {
			array_push( $matches, '' );
		}

		$wikiText = implode( "''", $matches );

		return $wikiText;
	}
}
