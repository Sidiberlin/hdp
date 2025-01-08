<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

interface IModule {

	/**
	 * @return IAnalyzer
	 */
	public function getAnalyzer(): IAnalyzer;

	/**
	 * @return IConverter
	 */
	public function getConverter(): IConverter;

	/**
	 * @return bool
	 */
	public function canHandle(): bool;

	/**
	 * @param Workspace $workspace
	 * @return void
	 */
	public function setWorkspace( $workspace );

	/**
	 * @return array
	 */
	public function getSupportedMimeTypes(): array;

	/**
	 * @return array
	 */
	public function getSupportedFileExtensions(): array;
}
