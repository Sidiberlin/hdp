<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

class SanitizeHeading {

	/**
	 * @var array
	 */
	private $wikiMarkup = [
		"\'\'\'",
		"\'\'"
	];

	/**
	 * @param string $level
	 * @param string $wikiText
	 * @return string
	 */
	public function execute( $level, $wikiText ): string {
		if ( $level === 0 ) {
			return $wikiText;
		}

		// Find headings
		$eq = '';
		for ( $index = 0; $index < $level; $index++ ) {
			$eq .= '=';
		}
		$regEx = "#^={" . $level . "}([^=].*?[^=])={" . $level . "}#m";
		$matches = [];
		$status = preg_match_all( $regEx, $wikiText, $matches );

		if ( !$status || ( $status === 0 ) ) {
			return $wikiText;
		}

		// Sanitize headings
		$countMatches = count( $matches[0] );

		for ( $index = 0; $index < $countMatches; $index++ ) {
			$curHeading = $matches[0][$index];
			$curText = $matches[1][$index];

			$text = $this->removeHtml( $curText );
			$text = $this->removeWikiMarkup( $text );
			// Do not trim wikiText. This prevent removing headings.
			$curHeading = preg_quote( $curHeading );
			$wikiText = preg_replace( "#$curHeading#m", $eq . $text . $eq, $wikiText );

		}

		return $wikiText;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function removeHtml( $text ): string {
		$matches = [];

		// Remove marker html
		$text = preg_replace( "#(<[^>]*?><\/[^>]*?>)#m", '', $text );

		// Remove wrapper html
		$status = preg_match_all( "#(<[^>]*?>)(.*?)(<\/[^>]*?>)#m", $text, $matches );
		if ( !$status || ( $status === 0 ) ) {
			return $text;
		}

		$countMatches = count( $matches[0] );

		for ( $index = 0; $index < $countMatches; $index++ ) {
			$curMatch = preg_quote( $matches[0][$index] );
			$text = preg_replace( "#$curMatch#m", $matches[2][$index], $text );
		}

		return $text;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function removeWikiMarkup( $text ): string {
		foreach ( $this->wikiMarkup as $wikiMarkup ) {
			$matches = [];
			$status = preg_match_all( "#($wikiMarkup)(.*?)($wikiMarkup)#m", $text, $matches );

			if ( !$status || ( $status === 0 ) ) {
				continue;
			}

			$countMatches = count( $matches[0] );

			for ( $index = 0; $index < $countMatches; $index++ ) {
				$curMatch = preg_quote( $matches[0][$index] );
				$text = preg_replace( "#$curMatch#m", $matches[2][$index], $text );
			}
		}
		return $text;
	}
}
