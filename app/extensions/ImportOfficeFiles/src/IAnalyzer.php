<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

interface IAnalyzer {

	/**
	 * Return a list of informaton like generated page titles,
	 * generated file names of e.g. images, ...
	 *
	 * @param Workspace $workspace
	 * @return AnalyzerResult
	 */
	public function analyze( $workspace ): AnalyzerResult;
}
