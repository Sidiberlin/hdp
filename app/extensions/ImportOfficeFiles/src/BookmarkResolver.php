<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

class BookmarkResolver {

	/**
	 * Find <span class"bookmark-start" id="..."></span> and corresponding link
	 * and build propper link target
	 *
	 * @param SegmentList $segmentList
	 * @return SegmentList
	 */
	public function resolve( SegmentList $segmentList ): SegmentList {
		$bookmarkStartList = $this->buildBookmarkStartList( $segmentList );
		$resolvedSegmentList = $this->resolveBookmarks( $segmentList, $bookmarkStartList );

		return $resolvedSegmentList;
	}

	/**
	 * @param SegmentList $segmentList
	 * @return array
	 */
	private function buildBookmarkStartList( SegmentList $segmentList ): array {
		$list = [];
		for ( $index = 0; $index < $segmentList->count(); $index++ ) {
			/** @var Segment */
			$segment = $segmentList->item( $index );

			$label = $segment->getLabel();
			$filePath = $segment->getFilePath();
			$wikiText = file_get_contents( $filePath );

			$regEx = 'span class="bookmark-start" id="(.*?)"';
			$matches = [];
			$status = preg_match_all( "/$regEx/", $wikiText, $matches );

			if ( $status ) {
				foreach ( $matches[1] as $id ) {
					$list[$id] = $label;
				}
			}
		}
		return $list;
	}

	/**
	 * @param SegmentList $segmentList
	 * @param array $bookmarkStartList
	 * @return SegmentList
	 */
	private function resolveBookmarks( SegmentList $segmentList, array $bookmarkStartList ): SegmentList {
		for ( $index = 0; $index < $segmentList->count(); $index++ ) {
			/** @var Segment */
			$segment = $segmentList->item( $index );

			$filePath = $segment->getFilePath();
			$wikiText = file_get_contents( $filePath );

			$regEx = '\[\[#(.*?)\|(.*?)\]\]';
			$matches = [];
			$status = preg_match_all( "/$regEx/", $wikiText, $matches );

			if ( $status ) {
				for ( $matchIndex = 0; $matchIndex < count( $matches[0] ); $matchIndex++ ) {
					$id = $matches[1][$matchIndex];
					if ( isset( $bookmarkStartList[$id] ) ) {
						$search = $matches[0][$matchIndex];
						$label = $matches[2][$matchIndex];
						$replacement = '[[' . $bookmarkStartList[$id] . '#' . $id . '|' . $label . ']]';
						$wikiText = str_replace( $search, $replacement, $wikiText );
					}
				}
			}

			file_put_contents( $filePath, $wikiText );
		}

		return $segmentList;
	}
}
