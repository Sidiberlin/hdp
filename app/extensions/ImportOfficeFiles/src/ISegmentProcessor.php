<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

interface ISegmentProcessor {

	/**
	 * @param Segment $segment
	 * @return string
	 */
	public function process( Segment $segment ): Segment;
}
