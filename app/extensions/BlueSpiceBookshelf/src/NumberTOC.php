<?php

namespace BlueSpice\Bookshelf;

class NumberTOC {

	/**
	 * @param string $articleNumber
	 * @param string $html
	 * @return string
	 */
	public function execute( string $articleNumber, string $html ): string {
		$regEx = '#<span class="tocnumber">([\d\.]*?)</span>\s*?<span class="toctext">(.*?)</span>#';

		$matches = [];
		$status = preg_match_all( $regEx, $html, $matches );
		if ( !$status ) {
			return $html;
		}

		for ( $index = 0; $index < count( $matches[0] ); $index++ ) {
			$tocNumber = $matches[1][$index];
			$text = $matches[2][$index];

			$html = preg_replace(
				$this->getReplacementRegEx( $tocNumber, $text ),
				$this->getReplacementHtml( $articleNumber, $tocNumber, $text ),
				$html
			);
		}

		return $html;
	}

	/**
	 * @param string $tocNumber
	 * @param string $text
	 * @return string
	 */
	private function getReplacementRegEx( string $tocNumber, string $text ): string {
		$regEx = '#<span class="tocnumber">' . preg_quote( $tocNumber, '#' );
		$regEx .= '</span> <span class="toctext">' . preg_quote( $text, '#' ) . '</span>#';

		return $regEx;
	}

	/**
	 * @param string $articleNumber
	 * @param string $tocNumber
	 * @param string $text
	 * @return string
	 */
	private function getReplacementHtml( string $articleNumber, string $tocNumber, string $text ): string {
		$html = '<span class="tocnumber">';
		$html .= $articleNumber . '.' . $tocNumber;
		$html .= '</span> <span class="toctext">' . $text . '</span>';

		return $html;
	}

}
