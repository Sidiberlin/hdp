<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Process;

use Exception;
use MediaWiki\Extension\ImportOfficeFiles\AnalyzerResult;
use MediaWiki\Extension\ImportOfficeFiles\ConverterResult;
use MediaWiki\Extension\ImportOfficeFiles\IModule;
use MediaWiki\Extension\ImportOfficeFiles\ModuleFactory;
use MediaWiki\Extension\ImportOfficeFiles\Modules\MSOfficeWord;
use MediaWiki\Extension\ImportOfficeFiles\Workspace;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use SplFileInfo;

class FileConvertProcessStep implements IProcessStep, LoggerAwareInterface {

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var IModule
	 */
	private $module;

	/**
	 * @var Workspace
	 */
	private $workspace;

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
	private $baseTitle;

	/**
	 * @var int
	 */
	private $split;

	/**
	 * @var bool
	 */
	private $uncollide;

	/**
	 * @var string
	 */
	private $uploadId;

	/**
	 * @var string
	 */
	private $username;

	/**
	 * @param string $uploadId
	 * @param string $file
	 * @param string $title
	 * @param string $structure
	 * @param string $conflict
	 * @param string $username
	 * @throws Exception
	 */
	public function __construct(
		string $uploadId,
		string $file,
		string $title,
		string $structure,
		string $conflict,
		string $username
	) {
		$config = MediaWikiServices::getInstance()->getMainConfig();

		// We need integer "split" parameter
		$splitMap = [
			'h1' => 2,
			'h2' => 3,
			'h3' => 4,
			'h4' => 5,
			'h5' => 6
		];

		if ( $structure ) {
			$this->split = $splitMap[$structure];
		} else {
			$this->split = 0;
		}

		if ( $conflict === 'rename' ) {
			$this->uncollide = true;
		} else {
			$this->uncollide = false;
		}

		$workspaceDirectory = $config->get( 'UploadDirectory' ) . '/cache/ImportOfficeFiles';

		$this->uploadId = $uploadId;

		$this->destination = $workspaceDirectory;
		$sourceFilePath = trim( "$workspaceDirectory/$uploadId/upload/$file" );

		$this->baseTitle = $title;

		if ( !file_exists( $sourceFilePath ) ) {
			throw new Exception( 'File does not exist' );
		}
		$this->source = new SplFileInfo( $sourceFilePath );

		$this->username = $username;

		$logger = LoggerFactory::getInstance( 'ImportOfficeFiles' );
		$this->setLogger( $logger );

		$this->logger->debug( "Convert process launched." );
	}

	/**
	 * @param array $data
	 * @return array
	 */
	public function execute( $data = [] ): array {
		$this->setWorkspace( $this->destination );
		if ( !$this->source ) {
			throw new Exception( 'Source file not found' );
		}
		$this->logger->debug( "Source file: '{$this->source->getFilename()}'..." );

		$this->source = $this->workspace->uploadSourceFile( $this->source );
		if ( $this->source === null ) {
			throw new Exception( 'Source file failed to upload into workspace directory' );
		}

		$services = MediaWikiServices::getInstance();
		$titleFactory = $services->get( 'TitleFactory' );
		$title = $titleFactory->newFromText( $this->baseTitle );
		$titleText = $title->getText();
		$namespace = $title->getNamespace();
		$namespaceText = '';
		if ( $namespace !== NS_MAIN && $title->isContentPage() ) {
			$namespaceText = $title->getNsText();
		}

		$titleParts = explode( ':', $title );
		if ( count( $titleParts ) > 1 ) {
			$namespace = array_pop( $titleParts );
			$title = implode( '_', $titleParts );
		}

		$this->workspace->addToBucket(
			MSOfficeWord::BUCKET_ANALYZER_PARAMS,
			[
				'namespace' => $namespaceText,
				'base-title' => $titleText,
				'verbose' => false,
				'split' => $this->split,
				'ns-filerepo-compat' => 'false',
				'uncollide' => $this->uncollide,
				'categories' => [],
			]
		);
		$this->workspace->saveBucket( MSOfficeWord::BUCKET_ANALYZER_PARAMS );

		$moduleFactory = new ModuleFactory();
		$this->module = $moduleFactory->getModule( $this->workspace );

		$analyzerResult = $this->runAnalyzer( $this->workspace );
		$converterResult = $this->runConvertStep( $this->workspace, $analyzerResult );

		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * @param string $path
	 * @return void
	 */
	private function setWorkspace( $path ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$this->workspace = new Workspace( $config );
		$workspaceInfo = $this->workspace->init( $this->uploadId, $path );
		$workspacePath = $workspaceInfo['id'];

		$this->logger->debug( "Workspace: '{$workspacePath}'" );
	}

	/**
	 * @param Workspace $workspace
	 * @return AnalyzerResult
	 */
	private function runAnalyzer( Workspace $workspace ): AnalyzerResult {
		$this->logger->debug( "Start analyzing ..." );

		if ( $this->module === null ) {
			$this->logger->debug( "No module found." );
			return false;
		}

		$analyzer = $this->module->getAnalyzer();
		$result = $analyzer->analyze( $workspace );
		$status = $result->getStatus();

		if ( $status ) {
			$this->logger->debug( "Analyzing done." );
		} else {
			$this->logger->error( "Analyzing failed." );
		}

		return $result;
	}

	/**
	 * @param Workspace $workspace
	 * @param AnalyzerResult $result
	 * @return ConverterResult
	 */
	private function runConvertStep( Workspace $workspace, AnalyzerResult $result ): ConverterResult {
		$this->logger->debug( "Start conversion ..." );
		$params = [
			'filename' => $result->getFilename(),
			'namespace' => $result->getNamespace(),
			'base-title' => $result->getBaseTitle(),
			'verbose' => false,
			'split' => $result->getSplit(),
			'categories' => $result->getCategories(),
			'ns-filerepo-compat' => $result->getNsFileRepoCompat(),
			'title-map' => $result->getTitleMap(),
			'username' => $this->username
		];

		$workspace->addToBucket( MSOfficeWord::BUCKET_CONVERTER_PARAMS, $params );
		$workspace->saveBucket( MSOfficeWord::BUCKET_CONVERTER_PARAMS );
		$converter = $this->module->getConverter();
		$result = $converter->convert( $workspace );
		$status = $result->getStatus();

		if ( $status ) {
			$this->logger->debug( "Conversion done." );
		} else {
			$this->logger->error( "Conversion failed." );
		}

		return $result;
	}
}
