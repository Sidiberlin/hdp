<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Process\ImportProcess;

use Exception;
use MediaWiki\Extension\ImportOfficeFiles\Integration\BlueSpiceFarmingTrait;
use MediaWiki\Extension\ImportOfficeFiles\Workspace;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ImportPagesStep implements InterruptingProcessStep, LoggerAwareInterface {

	use BlueSpiceFarmingTrait;

	/**
	 * @var Workspace
	 */
	private $workspace;

	/**
	 * @var string
	 */
	private $importXmlScript;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param string $uploadId
	 * @param string $uploadDirectory
	 * @param string $importXmlScript
	 */
	public function __construct( string $uploadId, string $uploadDirectory, string $importXmlScript ) {
		$workspaceDir = $uploadDirectory . '/cache/ImportOfficeFiles';

		$config = MediaWikiServices::getInstance()->getMainConfig();

		$this->workspace = new Workspace( $config );
		$this->workspace->init( $uploadId, $workspaceDir );

		$this->importXmlScript = $importXmlScript;

		$logger = LoggerFactory::getInstance( 'ImportOfficeFiles_UI' );
		$this->setLogger( $logger );

		$this->logger->debug( "Start importing pages for upload ID '$uploadId'." );
	}

	/**
	 * @param LoggerInterface $logger
	 * @return void
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * @param array $data
	 * @return array
	 */
	public function execute( $data = [] ): array {
		$importXmlPath = $this->workspace->getPath() . '/result/import.xml';

		$this->logger->debug( 'Importing pages from XML: ' . $importXmlPath );

		$params = [
			$GLOBALS['wgPhpCli'],
			$this->importXmlScript,
			$importXmlPath,
			'--username-prefix=""'
		];
		$this->extendParams( $params );

		try {
			// php {$IP}/maintenance/importDump.php images/cache/ImportOfficeFiles/{uploadId}/result/import.xml
			$processPages = new Process( $params );
			$processPages->run();
		} catch ( Exception $e ) {
			return [ 'output_pages' => $e->getMessage() ];
		}

		if ( !$processPages->isSuccessful() ) {
			throw new ProcessFailedException( $processPages );
		}

		$this->logger->debug( 'Pages imported.' );
		$this->logger->debug( 'Output of "importDump.php" script: ' . "\n" . $processPages->getOutput() );

		return [
			'output_pages' => $processPages->getOutput()
		];
	}

}
