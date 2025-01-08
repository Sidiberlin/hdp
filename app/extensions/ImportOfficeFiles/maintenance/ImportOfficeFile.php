<?php

use MediaWiki\Extension\ImportOfficeFiles\AnalyzerResult;
use MediaWiki\Extension\ImportOfficeFiles\ConverterResult;
use MediaWiki\Extension\ImportOfficeFiles\IModule;
use MediaWiki\Extension\ImportOfficeFiles\ModuleFactory;
use MediaWiki\Extension\ImportOfficeFiles\Modules\MSOfficeWord;
use MediaWiki\Extension\ImportOfficeFiles\Workspace;
use MediaWiki\MediaWikiServices;

$maintPath = ( getenv( 'MW_INSTALL_PATH' ) !== false
		? getenv( 'MW_INSTALL_PATH' )
		: __DIR__ . '/../../..' ) . '/maintenance/Maintenance.php';
if ( !file_exists( $maintPath ) ) {
	echo "Please set the environment variable MW_INSTALL_PATH "
		. "to your MediaWiki installation.\n";
	exit( 1 );
}
require_once $maintPath;

/**
 * @ingroup Maintenance
 * @since 1.32
 */
class ImportOfficeFile extends Maintenance {

	/**
	 * @var string
	 */
	private $destination;

	/**
	 * @var SplFileInfo
	 */
	private $source;

	/**
	 * @var string
	 */
	private $namespace;

	/**
	 * @var string
	 */
	private $baseTitle;

	/**
	 * @var int
	 */
	private $split;

	/**
	 * @var IModule
	 */
	private $module = null;

	/**
	 * @var Workspace
	 */
	private $workspace;

	/**
	 * @var string
	 */
	private $nsFileRepoCompat = 'false';

	/**
	 * @var bool
	 */
	private $uncollide = false;

	/**
	 */
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'ImportOfficeFiles' );
		$this->addDescription( 'Import an office file' );
		$this->addOption( 'src', 'Name of the file', true, true );
		$this->addOption( 'dest', 'Path to working directory', true, true );
		$this->addOption( 'namespace', 'Namespace number', false, true );
		$this->addOption( 'title', 'Root title', false, true );
		$this->addOption( 'split', 'Split at heading 2, 3, 4, 5 or 6', false, true );
		$this->addOption( 'ns-filerepo-compat', 'NsFileRepo compatibility (true/false)', false, true );
		$this->addOption( 'uncollide', 'Uncollide wiki page titles (true/false)', false, true );
	}

	/**
	 * @return void
	 */
	public function execute() {
		$this->fetchOptions();
		$this->setWorkspace( $this->destination );
		if ( !$this->source ) {
			$this->output( "Source file not found\n\n" );
			exit( 1 );
		}
		$this->output( "Source file: '{$this->source->getFilename()}'...\n\n" );
		$this->source = $this->workspace->uploadSourceFile( $this->source );

		$this->workspace->addToBucket(
			MSOfficeWord::BUCKET_ANALYZER_PARAMS,
			[
				'namespace' => $this->namespace,
				'base-title' => $this->baseTitle,
				'verbose' => true,
				'split' => $this->split,
				'ns-filerepo-compat' => $this->nsFileRepoCompat,
				'categories' => [],
				'uncollide' => $this->uncollide
			]
		);
		$this->workspace->saveBucket( MSOfficeWord::BUCKET_ANALYZER_PARAMS );

		$moduleFactory = new ModuleFactory();
		$this->module = $moduleFactory->getModule( $this->workspace );

		if ( $this->module !== null ) {
			$analyzerResult = $this->runAnalyzer( $this->workspace );
			$converterResult = $this->runConvertStep( $this->workspace, $analyzerResult );
		} else {
			throw new Exception( "No module defined for this file type" );
		}
	}

	/**
	 * @return void
	 */
	private function fetchOptions() {
		if ( !file_exists( trim( $this->getOption( 'src' ) ) ) ) {
			$this->output( "File does not exist\n" );
			exit( 1 );
		}
		$this->source = new SplFileInfo( $this->getOption( 'src' ) );

		$this->destination = $this->getOption( 'dest', false );
		if ( !$this->destination ) {
			$this->output( "No destination path set\n\n" );
		}

		$this->namespace = $this->getOption( 'namespace', '' );

		$this->baseTitle = $this->getOption( 'title', false );

		$this->split = (int)$this->getOption( 'split', 0 );
		if ( $this->split !== 0 && ( $this->split < 2 || $this->split > 6 ) ) {
			$this->output( "Value for split option not allowed\n\n" );
			exit( 1 );
		}

		$nsFileRepoCompat = $this->getOption( 'ns-filerepo-compat', 'false' );
		if ( $nsFileRepoCompat === 'true' ) {
			$this->nsFileRepoCompat = 'true';
		}

		$uncollide = $this->getOption( 'uncollide', 'false' );
		if ( $uncollide === 'true' ) {
			$this->uncollide = 'true';
		}
	}

	/**
	 * @param string $path
	 * @return void
	 */
	private function setWorkspace( $path ) {
		$services = MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'bsg' );
		$this->workspace = new Workspace( $config );
		$workspaceInfo = $this->workspace->init( 'ImportOffice', $path );
		$workspacePath = $workspaceInfo['id'];
		$this->output( "Workspace: '{$workspacePath}'\n" );
	}

	/**
	 * @param Workspace $workspace
	 * @return AnalyzerResult
	 */
	private function runAnalyzer( Workspace $workspace ): AnalyzerResult {
		$this->output( "\nStart analyzing ...\n" );
		$analyzer = $this->module->getAnalyzer();
		$result = $analyzer->analyze( $workspace );
		$status = $result->getStatus();

		$this->output( "\nAnalyzing done\n" );
		return $result;
	}

	/**
	 * @param Workspace $workspace
	 * @param AnalyzerResult $result
	 * @return ConverterResult
	 */
	private function runConvertStep( Workspace $workspace, AnalyzerResult $result ): ConverterResult {
		$this->output( "\nStart convertion ...\n" );
		$params = [
			'filename' => $result->getFilename(),
			'namespace' => $result->getNamespace(),
			'base-title' => $result->getBaseTitle(),
			'verbose' => true,
			'split' => $result->getSplit(),
			'categories' => $result->getCategories(),
			'ns-filerepo-compat' => $result->getNsFileRepoCompat(),
			'title-map' => $result->getTitleMap()
		];
		$workspace->addToBucket( MSOfficeWord::BUCKET_CONVERTER_PARAMS, $params );
		$workspace->saveBucket( MSOfficeWord::BUCKET_CONVERTER_PARAMS );
		$extractor = $this->module->getConverter();
		$result = $extractor->convert( $workspace );
		$status = $result->getStatus();

		$this->output( "\nConvertion done\n" );
		return $result;
	}
}

$maintClass = ImportOfficeFile::class;
require_once RUN_MAINTENANCE_IF_MAIN;
