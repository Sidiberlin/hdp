<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use SplFileInfo;
use Wikimedia\Mime\MimeAnalyzer;

class MimeValidator implements IModuleMimeValidator {

	/**
	 * @var IModule
	 */
	private $module;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @param IModule $module
	 * @param Config $config
	 */
	public function __construct( IModule $module, Config $config ) {
		$this->module = $module;
		$this->config = $config;
	}

	/**
	 * @param SplFileInfo $file
	 * @return bool
	 */
	public function canHandle( $file ): bool {
		$verifyMimeType = $this->config->get( 'VerifyMimeType' );

		if ( $verifyMimeType === true ) {
			// Mime type check can be disabled because not all docx documents do have the correct mimetype.

			$fileExtension = $file->getExtension();
			$fileExtension = strtolower( $fileExtension );

			$supportedFileExtensions = $this->module->getSupportedFileExtensions();
			$supportedFileExtensions = array_map( 'strtolower', $supportedFileExtensions );

			if ( in_array( $fileExtension, $supportedFileExtensions ) ) {
				return true;
			}

			return false;
		}

		$services = MediaWikiServices::getInstance();
		/** @var MimeAnalyzer */
		$mimeAnalyzer = $services->getService( 'MimeAnalyzer' );
		$mimeType = $mimeAnalyzer->getMimeTypeFromExtensionOrNull( $file->getExtension() );

		$supportedMimeTypes = $this->module->getSupportedMimeTypes();
		if ( in_array( $mimeType, $supportedMimeTypes ) ) {
			return true;
		}
		return false;
	}
}
