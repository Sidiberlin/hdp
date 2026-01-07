<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Process\ImportProcess;

use Exception;
use MediaWiki\Extension\ImportOfficeFiles\Integration\BlueSpiceFarmingTrait;
use MediaWiki\Extension\ImportOfficeFiles\Workspace;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ImportImagesStep implements InterruptingProcessStep, LoggerAwareInterface {
	use BlueSpiceFarmingTrait;

	/**
	 * @var Workspace
	 */
	private $workspace;

	/**
	 * @var string
	 */
	private $importImagesScript;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var string
	 */
	private $username = '';

	/**
	 * @param string $uploadId
	 * @param string $uploadDirectory
	 * @param string $importImagesScript
	 * @param string $username
	 */
	public function __construct(
		string $uploadId,
		string $uploadDirectory,
		string $importImagesScript,
		string $username
	) {
		$workspaceDir = $uploadDirectory . '/cache/ImportOfficeFiles';

		$config = MediaWikiServices::getInstance()->getMainConfig();

		$this->workspace = new Workspace( $config );
		$this->workspace->init( $uploadId, $workspaceDir );

		$this->importImagesScript = $importImagesScript;

		$this->username = $username;

		$logger = LoggerFactory::getInstance( 'ImportOfficeFiles_UI' );
		$this->setLogger( $logger );

		$this->logger->debug( "Start importing images for upload ID '$uploadId'." );
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
		$imagesDir = $this->workspace->getPath() . '/result/images/';

		$this->logger->debug( 'Importing images from directory: ' . $imagesDir );

		// Bail out instantly if there are no images to import, otherwise we'll get an exception.
		// It works so after this commit:
		// https://github.com/wikimedia/mediawiki/commit/5a7c5491775ebf97f60fc7067d3d41c609358534
		if ( $this->findImages( $imagesDir ) === [] ) {
			return [
				// That's mostly message for developers, no need to make translate-able
				'output_images' => "No images were found to import.",
			];
		}

		// php {IP}/maintenance/importImages.php images/cache/ImportOfficeFiles/{uploadId}/result/images/
		$params = [
			$GLOBALS['wgPhpCli'],
			$this->importImagesScript,
			$imagesDir,
			'--summary= ',
			'--overwrite= '
		];
		$this->extendParams( $params );
		if ( $this->username !== '' ) {
			$params[] = '--user=' . $this->username;
		}
		$exceptions = "";
		try {
			$processImages = new Process( $params );
			$processImages->run();
		} catch ( Exception $e ) {
			$exceptions .= $e->getMessage();
		}
		if ( !$processImages->isSuccessful() ) {
			$this->logger->debug(
				'Exception while importing images in overwrite mode, retry without overwrite.'
			);
			// when uploading exactly the same file again,
			// the upload should be safely skipped without breaking the import.
			unset( $params[array_search( '--overwrite= ', $params )] );
			try {
				$processImages = new Process( $params );
				$processImages->run();
			} catch ( Exception $e ) {
				$exceptions .= $e->getMessage();
				return [
					'output_images_exception' => $exceptions
				];
			}
		}

		if ( !$processImages->isSuccessful() ) {
			throw new ProcessFailedException( $processImages );
		}

		$this->logger->debug( 'Images imported.' );
		$this->logger->debug( 'Output of "importImages.php" script: ' . "\n" . $processImages->getOutput() );

		return [
			'output_images' => $processImages->getOutput()
		];
	}

	/**
	 * Search a directory for files with one of a set of extensions.
	 *
	 * Almost full C&P from "maintenance/importImages.php" ( \ImportImages::findFiles ).
	 *
	 * @param string $dir Path to directory to search
	 * @return array Array of filenames on success, or false on failure
	 */
	private function findImages( $dir ) {
		if ( !is_dir( $dir ) ) {
			return [];
		}

		$dhl = opendir( $dir );
		if ( !$dhl ) {
			return [];
		}

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$extensions = $config->get( MainConfigNames::FileExtensions );

		$files = [];

		$file = readdir( $dhl );
		while ( $file !== false ) {
			if ( is_file( $dir . '/' . $file ) ) {
				$ext = pathinfo( $file, PATHINFO_EXTENSION );
				if ( in_array( strtolower( $ext ), $extensions ) ) {
					$files[] = $dir . '/' . $file;
				}
			}

			$file = readdir( $dhl );
		}

		return $files;
	}

}
