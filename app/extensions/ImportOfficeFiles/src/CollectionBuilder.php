<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

use MediaWiki\MediaWikiServices;

class CollectionBuilder {

	/**
	 * @var array
	 */
	private $levelMap = [
		0 => '*',
		1 => '*',
		2 => '*',
		3 => '**',
		4 => '***',
		5 => '****',
		6 => '*****',
	];

	/**
	 * @param SegmentList $segments
	 * @return string
	 */
	public function build( SegmentList $segments ): string {
		$services = MediaWikiServices::getInstance();

		/** var TitleFactory */
		$titleFactory = $services->get( 'TitleFactory' );

		$wikiText = '';
		for ( $index = 0; $index < $segments->count(); $index++ ) {
			$segment = $segments->item( $index );
			// TODO: Improve level handling, for now level 1 (subtitle) is ignored for splitting
			// See WikiPageStructureBuilder
			$level = $segment->getLevel();
			if ( $level < 2 ) {
				$listLevel = $this->levelMap[$level];
			} else {
				$titleParts = explode( '/', $segment->getLabel() );
				$segmentlevel = count( $titleParts );
				$listLevel = $this->levelMap[$segmentlevel];
			}

			$title = $titleFactory->newFromText( $segment->getLabel() );
			$target = $title->getFullText();
			$titleText = $title->getText();

			$labelParts = explode( '/', $titleText );
			$label = array_pop( $labelParts );

			$wikiText .= $listLevel;
			$wikiText .= " [[$target|$label]]\n";
		}
		return $wikiText;
	}
}
