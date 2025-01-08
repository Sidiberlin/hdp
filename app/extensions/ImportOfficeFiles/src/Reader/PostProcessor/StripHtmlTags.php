<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\PostProcessor;

/**
 * TODO: Delete if not used
 *
 * Fixes cases like
 * "[https://www.google.com.ua/ <span style=\'color: #1155cc;background-color: #b7b7b7;\'>Some link</span>]"
 * "{{DISPLAYTITLE:<span style=\'color: #ffff00;\'>\'\'\'\'\'Title with styles\'\'\'\'\'</span>}}"
 * and similar by removing HTML tags.
 * Should be done as post-processing, when wikitext is ready.
 */
class StripHtmlTags {

	/**
	 * @param string $wikitext
	 * @return string
	 */
	public function process( string $wikitext ): string {
		$wikitext = preg_replace( '/\[\[(.*?)<.+?>(.*?)<\/.+>\]\]/m', '[[$1$2]]', $wikitext );
		$wikitext = preg_replace( '/\[{1}(.*?)<.+?>(.*?)<\/.+>\]/m', '[$1$2]', $wikitext );

		$wikitext = preg_replace_callback( '/\{\{(.*?)<.+?>(.*?)<\/.+>\}\}/m', static function ( $matches ) {
			return '{{' . $matches[1] . trim( $matches[2], '\'' ) . '}}';
		}, $wikitext );

		return $wikitext;
	}

}
