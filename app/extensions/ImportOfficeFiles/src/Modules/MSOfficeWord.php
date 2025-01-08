<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Modules;

use Config;
use MediaWiki\Extension\ImportOfficeFiles\Analyzer\MSOfficeWordAnalyzer;
use MediaWiki\Extension\ImportOfficeFiles\Converter\MSOfficeWordConverter;
use MediaWiki\Extension\ImportOfficeFiles\IAnalyzer;
use MediaWiki\Extension\ImportOfficeFiles\IConverter;
use MediaWiki\Extension\ImportOfficeFiles\IModule;
use MediaWiki\Extension\ImportOfficeFiles\IModuleMimeValidator;
use MediaWiki\Extension\ImportOfficeFiles\MimeValidator;

class MSOfficeWord implements IModule {

	public const BUCKET_ANALYZER = 'analyzer';
	public const BUCKET_ANALYZER_PARAMS = 'analyzer-params';
	public const BUCKET_CONVERTER = 'converter';
	public const BUCKET_CONVERTER_PARAMS = 'converter-params';
	public const BUCKET_CONVERTER_MEDIA_MAP = 'converter-media-map';
	public const BUCKET_IMPORT_MEDIA_FILENAME_FILEPATH = 'import-media-filename-filepath';
	public const BUCKET_MEDIA_FILENAME_FILEPATH = 'media-filename-filepath';
	public const BUCKET_MEDIA_ID_FILENAME = 'media-id-filename';
	public const BUCKET_MEDIA_FILE_EXTENSIONS = 'media-file-extensions';
	public const BUCKET_STYLES = 'styles';
	public const BUCKET_RELATIONS = 'relations';
	public const BUCKET_SEGMENTS = 'segments';
	public const BUCKET_TITLE_MAP = 'title-map';
	public const BUCKET_CONVERTED_TITLE_FILEPATH = 'import-title-filepath';

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var Workspace
	 */
	protected $workspace = null;

	/**
	 *
	 * @param Config $config
	 */
	public function __construct( $config ) {
		$this->config = $config;
	}

	/**
	 * @param Config $config
	 * @return IModule
	 */
	public static function factory( Config $config ): IModule {
		return new static( $config );
	}

	/**
	 * @return bool
	 */
	public function canHandle(): bool {
		$mimeValidator = $this->getMimeValidator();
		return $mimeValidator->canHandle( $this->workspace->getSourceFile(), $this->config );
	}

	/**
	 * @return IAnalyzer
	 */
	public function getAnalyzer(): IAnalyzer {
		return new MSOfficeWordAnalyzer();
	}

	/**
	 * @return IConverter
	 */
	public function getConverter(): IConverter {
		return new MSOfficeWordConverter();
	}

	/**
	 * @return IModuleMimeValidator
	 */
	private function getMimeValidator(): IModuleMimeValidator {
		return new MimeValidator( $this, $this->config );
	}

	/**
	 * @param Workspace $workspace
	 * @return void
	 */
	public function setWorkspace( $workspace ) {
		$this->workspace = $workspace;
	}

	/**
	 * @return array
	 */
	public function getSupportedMimeTypes(): array {
		return [ 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ];
	}

	/**
	 * @return array
	 */
	public function getSupportedFileExtensions(): array {
		return [ 'docx' ];
	}
}
