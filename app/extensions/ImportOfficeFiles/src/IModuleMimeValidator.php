<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

interface IModuleMimeValidator {

	/**
	 * @param SplFileInfo $file
	 * @return bool
	 */
	public function canHandle( $file ): bool;
}
