<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag;

class HtmlWrapper {

	/**
	 * HTML tag which wikitext will be wrapped into.
	 * Could be 'span' or 'div'
	 *
	 * @var string
	 */
	private $htmlTag;

	/**
	 * Stores CSS styles which should be applied to wikitext when wrapping.
	 * Structure is like that:
	 * [
	 * 		<style_name1> => <style_value1>,
	 * 		<style_name2> => <style_value2>,
	 * 		...
	 * ]
	 *
	 * @var array
	 */
	private $styles = [];

	/**
	 * @param string $htmlTag HTML tag to wrap into. Could be 'span' or 'div'
	 */
	public function __construct( string $htmlTag ) {
		$this->htmlTag = $htmlTag;
	}

	/**
	 * Sets specific styles into wrapper
	 *
	 * @param array $styles Array, where key is style name and value is style value
	 * @return void
	 */
	public function setStyles( array $styles ): void {
		foreach ( $styles as $styleName => $value ) {
			$this->styles[$styleName] = $value;
		}
	}

	/**
	 * Wraps wikitext into some HTML tag, applying specific CSS styles
	 *
	 * @param string $wikitext Wikitext to wrap
	 * @return string Resulting wikitext
	 */
	public function wrap( string $wikitext ): string {
		$stylesText = $this->composeStyles();

		if ( !empty( $stylesText ) ) {
			$wikitext = "<{$this->htmlTag} style='$stylesText'>" . $wikitext . "</{$this->htmlTag}>";
		}

		return $wikitext;
	}

	/**
	 * Composes styles array into string
	 *
	 * @return string String containing CSS styles
	 */
	private function composeStyles(): string {
		$stylesText = '';
		if ( $this->styles ) {
			foreach ( $this->styles as $styleName => $value ) {
				$stylesText .= "$styleName: $value;";
			}
		}

		return $stylesText;
	}

}
