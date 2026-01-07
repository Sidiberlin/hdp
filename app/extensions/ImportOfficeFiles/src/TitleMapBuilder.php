<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class TitleMapBuilder {

	/**
	 * @param SegmentList $segments
	 * @param string $baseTitle
	 * @param string $namespace
	 * @return array
	 */
	public function build( SegmentList $segments, $baseTitle, $namespace = '' ): array {
		// TODO: Delete if not used

		$titleMap = [];
		if ( $segments->count() === 0 ) {
			return $titleMap;
		}

		if ( $segments->item( 0 )->getLevel() === 0 ) {
			$segments->replace( 0, new Segment(
				$segments->item( 0 )->getLevel(),
				$baseTitle,
				$segments->item( 0 )->getFilePath()
			) );
		}

		$title = [];
		$level = 0;
		for ( $index = 0; $index < $segments->count(); $index++ ) {
			// TODO: Strip html and wiki markup form label
			$label = trim( $segments->item( $index )->getLabel() );
			$label = $this->makeLabelSafe( $label );

			if ( $level > 0 ) {
				while ( $level >= $segments->item( $index )->getLevel() ) {
					array_pop( $title );
					$level--;
				}
			}
			if ( $segments->item( $index )->getLevel() > 0 ) {
				$level = $segments->item( $index )->getLevel();
			}
			$title[] = $label;
			$titleText = implode( '/', $title );

			$titleMap[] = $this->ensureTitle( $namespace, $titleText );
		}
		return $titleMap;
	}

	/**
	 *
	 * @param string $namespace
	 * @param string $titleText
	 * @return string
	 */
	private function ensureTitle( string $namespace, string $titleText ): string {
		$ns = '';
		if ( !empty( $namespace ) ) {
			$ns = "{$namespace}:";
		}

		$services = MediaWikiServices::getInstance();

		/** var TitleFactory */
		$titleFactory = $services->get( 'TitleFactory' );

		$title = $titleFactory->newFromText( "{$ns}{$titleText}" );

		$count = 1;
		while ( $title->exists() ) {
			if ( empty( $namespace ) ) {
				$title = Title::newFromText( "{$titleText}_($count)" );
			} else {
				$title = Title::newFromText( "{$namespace}:{$titleText}_($count)" );
			}
			$count++;
		}

		return $title->getFullText();
	}

	/**
	 * Removing illegal chars or chars that may cause trouble
	 *
	 * @param string $label
	 * @return string
	 */
	private function makeLabelSafe( string $label ): string {
		$safeLabel = '';
		$safeLabel = str_replace( [ "/", ":", "\t", "\n" ], ' ', $label );
		return $safeLabel;
	}
}
