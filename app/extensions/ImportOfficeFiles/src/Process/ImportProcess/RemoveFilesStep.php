<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Process\ImportProcess;

use MediaWiki\Logger\LoggerFactory;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class RemoveFilesStep implements InterruptingProcessStep, LoggerAwareInterface {

	/**
	 * @var string
	 */
	private $workspaceDirectory;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param string $uploadId
	 * @param string $uploadDirectory
	 */
	public function __construct( string $uploadId, string $uploadDirectory ) {
		$this->workspaceDirectory = $uploadDirectory . '/cache/ImportOfficeFiles/' . $uploadId;

		$logger = LoggerFactory::getInstance( 'ImportOfficeFiles_UI' );
		$this->setLogger( $logger );

		$this->logger->debug( "Start removing files for upload ID '$uploadId'." );
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
		// Delete source and all intermediate (like data buckets) files and directories
		wfRecursiveRemoveDir( $this->workspaceDirectory );

		$this->logger->debug( 'Files removed.' );

		return [
			'success' => true
		];
	}

}
