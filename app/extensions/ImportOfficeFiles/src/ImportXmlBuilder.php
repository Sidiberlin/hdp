<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

class ImportXmlBuilder {

	/**
	 * @param string $title
	 * @param string $wikiText
	 * @param string $username
	 * @return string
	 */
	public function buildPageXml( $title, $wikiText, $username ): string {
		$contributor = '';
		if ( $username !== '' ) {
			$contributor = '
			<contributor>
				<username>' . $username . '</username>
			</contributor>';
		}

		$xml = '<page>
	<title>' . htmlspecialchars( $title ) . '</title>
	<revision>' . $contributor . '
		<model>wikitext</model>
		<format>text/x-wiki</format>
		<text bytes="' . strlen( $wikiText ) . '" xml:space="preserve">
		' . htmlspecialchars( $wikiText ) . '
		</text>
	</revision>
</page>';
		return $xml;
	}

	/**
	 * @param string[] $pageXmls
	 * @return string
	 */
	public function buildImportXml( $pageXmls ): string {
		$xml = '<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.11/" ';
		$xml .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
		$xml .= 'xsi:schemaLocation="http://www.mediawiki.org/xml/export-0.11/ ';
		$xml .= 'http://www.mediawiki.org/xml/export-0.11.xsd" ';
		$xml .= 'version="0.11" xml:lang="en-GB">';
		foreach ( $pageXmls as $pageXml ) {
			$xml .= "\n$pageXml";
		}
		$xml .= "\n</mediawiki>";
		return $xml;
	}
}
