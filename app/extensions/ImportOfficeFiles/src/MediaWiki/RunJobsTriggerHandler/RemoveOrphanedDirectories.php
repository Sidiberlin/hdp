<?php

namespace MediaWiki\Extension\ImportOfficeFiles\MediaWiki\RunJobsTriggerHandler;

use DateTime;
use DirectoryIterator;
use MediaWiki\Config\Config;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Status\Status;
use MWStake\MediaWiki\Component\RunJobsTrigger\IHandler;
use MWStake\MediaWiki\Component\RunJobsTrigger\Interval\OnceADay;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

final class RemoveOrphanedDirectories implements IHandler, LoggerAwareInterface {

	public const HANDLER_KEY = 'ext-importofficefiles-remove-orphaned-directories';

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var string
	 */
	private $uploadDirectory;

	/**
	 * @param Config $mainConfig
	 */
	public function __construct( Config $mainConfig ) {
		$this->uploadDirectory = $mainConfig->get( 'UploadDirectory' );

		$logger = LoggerFactory::getInstance( 'ImportOfficeFiles_RemoveOrphanedDirectories' );
		$this->setLogger( $logger );
	}

	/**
	 * @inheritDoc
	 */
	public function run() {
		$this->logger->info( "Start removal of orphaned 'ImportOfficeFiles' subdirectories..." );

		$importOfficeDir = $this->uploadDirectory . '/cache/ImportOfficeFiles';
		if ( !file_exists( $importOfficeDir ) ) {
			$this->logger->info( "'ImportOfficeFiles' directory does not exist, nothing to do here." );
			return Status::newGood();
		}

		$iterator = new DirectoryIterator( $importOfficeDir );
		foreach ( $iterator as $subDir ) {
			if ( $subDir->isDir() === true && $subDir->isDot() === false ) {
				$lastModificationTimestamp = $subDir->getMTime();

				$dt = new DateTime();
				$dt->setTimestamp( $lastModificationTimestamp );

				$dtNow = new DateTime();

				$diff = $dt->diff( $dtNow );
				if ( $diff->days >= 1 ) {
					$this->logger->info( "Directory '{$subDir->getFilename()}' is more then 1 day old. Removing..." );

					$subDirPath = $subDir->getPathname();
					unlink( $subDirPath );
				}
			}
		}

		return Status::newGood();
	}

	/**
	 * @inheritDoc
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * @inheritDoc
	 */
	public function getInterval() {
		return new OnceADay();
	}

	/**
	 * @inheritDoc
	 */
	public function getKey() {
		return self::HANDLER_KEY;
	}

}
