<?php

namespace MediaWiki\Extension\NumberHeadings;

class NumberHeadings {

	/** @var array */
	private $headingCounter = [ 0, 0, 0, 0, 0, 0, 0 ];

	/** @var int */
	private $curHighestLevel = 6;

	/**
	 * @param string $html
	 * @param string $prefix
	 * @return string
	 */
	public function execute( string $html, string $prefix = '' ): string {
		$headings = $this->buildHeadingMap( $html );
		$newHtml = $this->processHeadings( $headings, $html, $prefix );

		return $newHtml;
	}

	/**
	 * @param string $html
	 * @return array
	 */
	private function buildHeadingMap( string $html ): array {
		$regEx = '#<h(\d)>(.*?)<span class="mw-headline" id="(.*?)">(.*?)</span>(.*?)</h(\d)>#';

		$headings = [];
		$matches = [];
		$status = preg_match_all( $regEx, $html, $matches );
		if ( !$status ) {
			return $headings;
		}

		for ( $index = 0; $index < count( $matches[0] ); $index++ ) {
			$headings[] = [
				'search' => $matches[0][$index],
				'level' => (int)$matches[1][$index],
				'pre' => $matches[2][$index],
				'id' => $matches[3][$index],
				'text' => $matches[4][$index],
				'post' => $matches[5][$index],
			];
		}

		return $headings;
	}

	/**
	 * @param array $headings
	 * @param string $html
	 * @param string $prefix
	 * @return string
	 */
	private function processHeadings( array $headings, string $html, string $prefix ): string {
		for ( $index = 0; $index < count( $headings ); $index++ ) {
			$level = $headings[$index]['level'];

			if ( $this->curHighestLevel > $level ) {
				$this->curHighestLevel = $level;
			}

			$level = $level - $this->curHighestLevel;

			$this->increaseHeadingCounter( $level );
			$this->resetHeadingCounter( $level + 1 );
			$numbering = $this->getHeadingNumbering( $prefix );

			$html = preg_replace(
				$this->getReplacementRegEx( $headings[$index] ),
				$this->getReplacementHtml( $numbering, $headings[$index] ),
				$html
			);
		}

		return $html;
	}

	/**
	 * @param array $item
	 * @return string
	 */
	private function getReplacementRegEx( array $item ): string {
		$level = $item['level'];
		$id = $item['id'];

		$regEx = '#<h' . $level . '>(.*?)<span class="mw-headline" id="' . preg_quote( $id, '#' ) . '">';
		$regEx .= '(.*?)</span>.*?</h' . $level . '>#';

		return $regEx;
	}

	/**
	 * @param string $numbering
	 * @param array $item
	 * @return string
	 */
	private function getReplacementHtml( string $numbering, array $item ): string {
		$level = $item['level'];
		$id = $item['id'];
		$text = $item['text'];
		$pre = $item['pre'];
		$post = $item['post'];

		$html = '<h' . $level . '>';
		$html .= $pre;
		$html .= '<span class="mw-headline" id="' . $id . '">' . $numbering . $text . '</span>';
		$html .= $post;
		$html .= '</h' . $level . '>';

		return $html;
	}

	/**
	 * @param int $level
	 * @return void
	 */
	private function increaseHeadingCounter( int $level ): void {
		$this->headingCounter[$level]++;
	}

	/**
	 * @param int $level
	 * @return void
	 */
	private function resetHeadingCounter( int $level ): void {
		for ( $index = $level; $index <= 6; $index++ ) {
			$this->headingCounter[$index] = 0;
		}
	}

	/**
	 * @param string $prefix
	 * @return string
	 */
	private function getHeadingNumbering( string $prefix ): string {
		$counter = $this->clearHeadingCounter();
		$numbering = implode( '.', $counter );

		$html = $prefix . '<span class="mw-headline-number">' . $numbering . ' </span>';

		return $html;
	}

	/**
	 * @return array
	 */
	private function clearHeadingCounter(): array {
		$counter = $this->headingCounter;
		for ( $index = 6; $index >= 0; $index-- ) {
			if ( $counter[$index] === 0 ) {
				unset( $counter[$index] );
			}
		}

		return $counter;
	}

}
