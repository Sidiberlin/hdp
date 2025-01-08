<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

class SegmentList {

	/**
	 * @var array
	 */
	private $segments = [];

	/**
	 * @param Segment $segment
	 * @return void
	 */
	public function add( Segment $segment ): void {
		$this->segments[] = $segment;
	}

	/**
	 * @param int $index
	 * @param Segment $segment
	 * @return bool
	 */
	public function replace( int $index, Segment $segment ): bool {
		if ( isset( $this->segments[$index] ) ) {
			$this->segments[$index] = $segment;
			return true;
		}
		return false;
	}

	/**
	 * @param int $index
	 * @return Segment|null
	 */
	public function item( int $index ): ?Segment {
		if ( isset( $this->segments[$index] ) ) {
			return $this->segments[$index];
		}
		return null;
	}

	/**
	 * @return int
	 */
	public function count(): int {
		return count( $this->segments );
	}
}
