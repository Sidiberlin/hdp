<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

use MediaWiki\MediaWikiServices;

class SegmentsTitleBuilder {

	/**
	 * @var SegmentList|null
	 */
	private $segmentList = null;

	/**
	 * @var SegmentList|null
	 */
	private $srcSegments = null;

	/**
	 * @var string
	 */
	private $baseTitle = '';

	/**
	 * @var string
	 */
	private $namespace = '';

	/**
	 * @var bool
	 */
	private $hasNamespace = false;

	/**
	 * @var bool
	 */
	private $uncollide = false;

	/**
	 * @param SegmentList $segments
	 * @param string $baseTitle
	 * @param string $namespace
	 * @param bool $uncollide
	 */
	public function __construct( SegmentList $segments, $baseTitle = '', $namespace = '', $uncollide = false ) {
		$this->srcSegments = $segments;
		$this->baseTitle = $baseTitle;
		$this->uncollide = $uncollide;

		if ( $namespace !== '' ) {
			$this->namespace = $namespace;
			$this->hasNamespace = true;
		}

		if ( $this->srcSegments->count() > 0 ) {
			$this->replaceRootSegment();
		}
	}

	/**
	 * @return SegmentList
	 */
	public function buildSegmentList(): SegmentList {
		if ( $this->segmentList ) {
			return $this->segmentList;
		}

		$this->segmentList = new SegmentList();
		$titleParts = [];
		$level = 0;
		for ( $index = 0; $index < $this->srcSegments->count(); $index++ ) {
			$label = trim( $this->srcSegments->item( $index )->getLabel() );
			if ( $level > 0 ) {
				while ( $level >= $this->srcSegments->item( $index )->getLevel() ) {
					array_pop( $titleParts );
					$level--;
				}
			}

			if ( $this->srcSegments->item( $index )->getLevel() > 0 ) {
				$level = $this->srcSegments->item( $index )->getLevel();
			}

			$safeTitle = $this->makeTitleSafe( $label, $titleParts );
			$titleParts[] = $safeTitle['label'];
			$namespace = $safeTitle['namespace'];
			if ( $namespace !== '' ) {
				$namespace = "{$namespace}:";
			}

			$this->segmentList->add( new Segment(
				$level,
				$namespace . implode( '/', $titleParts ),
				$this->srcSegments->item( $index )->getFilePath()
			) );
		}
		return $this->segmentList;
	}

	/**
	 * @return array
	 */
	public function buildTitleMap(): array {
		if ( !$this->segmentList ) {
			$this->buildSegmentList();
		}

		$titleMap = [];
		for ( $index = 0; $index < $this->segmentList->count(); $index++ ) {
			$titleMap[] = $this->segmentList->item( $index )->getLabel();
		}

		return $titleMap;
	}

	/**
	 * @return void
	 */
	private function replaceRootSegment() {
		if ( $this->srcSegments->item( 0 )->getLevel() === 0 ) {
			$this->srcSegments->replace( 0, new Segment(
				$this->srcSegments->item( 0 )->getLevel(),
				$this->baseTitle,
				$this->srcSegments->item( 0 )->getFilePath()
			) );
		}
	}

	/**
	 * Removing illegal chars or chars that may cause trouble and handle title collision
	 *
	 * @param string $label
	 * @param array $titleParts
	 * @return array
	 */
	private function makeTitleSafe( string $label, array $titleParts ): array {
		// Remove wikitext placeholder
		$label = preg_replace( "/###PRESERVE(.*?)###/", '', $label );

		// Remove illegal chars
		$illegalChars = [
			"/", ":", "\t", "\n",
			"<", ">", "[", "]",
			"#", "{", "}", "|"
		];
		$label = str_replace( $illegalChars, ' ', $label );

		$titleParts[] = $label;
		$titleText = implode( "/", $titleParts );

		$services = MediaWikiServices::getInstance();

		$titleFactory = $services->getTitleFactory();

		if ( $this->hasNamespace ) {
			$title = $titleFactory->newFromText( "{$this->namespace}:{$titleText}" );

			if ( $title->getNsText() !== $this->namespace ) {
				// Namespace does not exist in wiki
				$this->hasNamespace = false;
				$title = $titleFactory->newFromText( "{$this->namespace}_{$titleText}" );
			}
		} else {
			$title = $titleFactory->newFromText( $titleText );
		}

		$titleText = $title->getText();

		if ( $this->uncollide ) {
			$count = 1;

			while ( $title->exists() ) {
				if ( $this->hasNamespace ) {
					$title = $titleFactory->newFromText( "{$this->namespace}:{$titleText}_($count)" );
				} else {
					$title = $titleFactory->newFromText( "{$titleText}_($count)" );
				}

				$count++;
			}
		}

		$safeNamespace = $title->getNsText();
		$safeTitleText = $title->getText();

		$safeLabelParts = explode( '/', $safeTitleText );
		$safeLabel = array_pop( $safeLabelParts );

		return [
			'namespace' => $safeNamespace,
			'label' => $safeLabel
		];
	}
}
