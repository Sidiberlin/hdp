<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

class WikiPageStructureBuilder {

	/**
	 * @param string $dir
	 * @param SegmentList $segments
	 * @param int $split
	 * @return SegmentList
	 */
	public function build( string $dir, SegmentList $segments, int $split ): SegmentList {
		$structureSegments = new SegmentList();

		if ( $segments->count() === 0 ) {
			return $structureSegments;
		}

		$count = 1;
		$level = $segments->item( 0 )->getLevel();
		$title = $segments->item( 0 )->getLabel();
		$wikiText = '';
		for ( $index = 0; $index < $segments->count(); $index++ ) {
			$curLevel = $segments->item( $index )->getLevel();
			// TODO: Improve level handling, for now level 1 is ignored for splitting
			// See: CollectionBuilder
			if ( $curLevel > 1 && $curLevel <= $split && $split !== 0 ) {
				$structureSegments->add( new Segment(
					$level,
					$title,
					"$dir/part-$count.structure"
				) );
				file_put_contents( "$dir/part-$count.structure", $wikiText );
				$wikiText = '';
				$title = $segments->item( $index )->getLabel();
				$level = $curLevel;
				$count++;
			}
			$wikiText .= file_get_contents( $segments->item( $index )->getFilePath() );
		}
		$structureSegments->add( new Segment(
			$level,
			$title,
			"$dir/part-$count.structure"
		) );
		file_put_contents( "$dir/part-$count.structure", $wikiText );

		return $structureSegments;
	}
}
