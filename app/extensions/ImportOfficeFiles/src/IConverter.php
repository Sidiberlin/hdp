<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

interface IConverter {

	/**
	 * Convert document to wiki text
	 *
	 * @param Workspace $workspace
	 * @return ConverterResult
	 */
	public function convert( $workspace ): ConverterResult;
}
