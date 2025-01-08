<?php

/**
 * Import images as NSFileRepo file.
 * Therefor the part of the filename till first underscore
 * is used as namespace.
 *
 * Expampe:
 * A file 'ABC_My_File.png' will be uploaded to the wiki as 'ABC:My File.png'
 */

require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/maintenance/Maintenance.php';

use MediaWiki\MediaWikiServices;

class ImportFiles extends Maintenance {

	/**
	 * @var string
	 */
	protected $src = '';

	/**
	 * @var array
	 */
	protected $errors = [];

	/**
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->addOption( 'overwrite', 'Overwrite existing files?' );
		$this->addOption( 'dry', 'Dry run. Do not actually upload files to the repo' );
		$this->addOption( 'summary', 'A summary for all file uploads' );
		$this->addOption( 'comment', 'A comment for all file uploads' );
		$this->addOption( 'verbose', 'More verbose output' );
		$this->addArg( 'dir', 'Path to the directory containing images to be imported' );
	}

	/**
	 * @return void
	 */
	public function execute() {
		$this->src = $this->getArg( 0 );

		$files = $this->getFileList();

		$processedFiles = 0;
		foreach ( $files as $fileName => $file ) {
			if ( $file instanceof SplFileInfo !== true ) {
				$this->error( 'Could not process list item: '
						. $fileName . ' '
						. var_export( $file, true )
				);
				continue;
			}
			$this->output( 'Processing ' . $file->getPathname() . " ... \n" );
			$mResult = $this->processFile( $file );
			if ( $mResult !== true ) {
				$this->error( " ... error: $mResult\n\n" );
			} else {
				$this->output( " ... done.\n\n" );
				$processedFiles++;
			}
		}

		$this->output( "$processedFiles file(s) processed.\n" );
		$this->output( count( $this->errors ) . " errors(s) occurred.\n" );
		if ( count( $this->errors ) > 0 ) {
			$this->output(
				implode( "\n", $this->errors )
			);
		}
	}

	/**
	 * @return array
	 */
	public function getFileList() {
		global $wgFileExtensions;
		$fileExtensions = array_map( 'strtolower', $wgFileExtensions );

		$realPath = realPath( $this->src );
		$this->output( 'Fetching file list from "' . $realPath . '"' );

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $realPath ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		$files = [];
		foreach ( $iterator as $realPath => $file ) {
			if ( $file instanceof SplFileInfo === false ) {
				$this->error( 'Not a valid SplFileInfo object: ' . $realPath );
			}
			if ( !empty( $fileExtensions ) ) {
				$fileExt = strtolower( $file->getExtension() );
				if ( !in_array( $fileExt,  $fileExtensions ) ) {
					continue;
				}
			}
			$files[$file->getPathname()] = $file;
		}

		ksort( $files, SORT_NATURAL );
		$fileCount = count( $files );
		$this->output( " ... found $fileCount file(s)\n" );

		return $files;
	}

	/**
	 * Throw an error to the user. Doesn't respect --quiet, so don't use
	 * this for non-error output
	 *
	 * @param string $error String: the error to display
	 * @param int $die Int: if > 0, go ahead and die out using this int as the code
	 */
	public function error( $error, $die = 0 ) {
		$this->errors[] = $error;
		parent::error( $error, $die );
	}

	/**
	 * @param SplFileInfo $file
	 * @return bool
	 */
	public function processFile( $file ) {
		$filename = $file->getFileName();
		$services = MediaWikiServices::getInstance();

		// NSFileRep: Use the text till first '_' as namespace
		$pos = strpos( $filename, '_' );
		if ( $pos !== false ) {
			$namespace = substr( $filename, 0, $pos );
			if ( $namespace !== false ) {
				$filename = str_replace( $namespace . '_', $namespace . ':', $filename );
			}
		}

		// MediaWiki normalizes multiple spaces/undescores into one single score/underscore
		$filename = str_replace( ' ', '_', $filename );
		$filename = preg_replace( '#(_)+#si', '_', $filename );

		$targetTitle = Title::makeTitle( NS_FILE, $filename );
		$repo = $services->getRepoGroup()->getLocalRepo();

		$this->output( "Using target title {$targetTitle->getPrefixedDBkey()} " );

		$repoFile = $repo->newFile( $targetTitle );
		if ( $repoFile->exists() ) {
			if ( !$this->hasOption( 'overwrite' ) ) {
				$this->output( "File '{$repoFile->getName()}' already exists. Skipping...\n" );
				return true;
			} else {
				$this->output( "File '{$repoFile->getName()}' already exists. Overwriting...\n" );
			}
		}

		/*
		 * The following code is almost a dirext copy of
		 * <mediawiki>/maintenance/importImages.php
		 */
		$commentText = $this->getOption( 'comment', '' );

		if ( !$this->hasOption( 'dry' ) ) {
			$mwProps = new MWFileProps( $services->getMimeAnalyzer() );
			$props = $mwProps->getPropsFromPath( $file->getPathname(), true );
			$flags = 0;
			$publishOptions = [];
			$handler = MediaHandler::getHandler( $props['mime'] );
			if ( $handler ) {
				$metadata = \Wikimedia\AtEase\AtEase::quietCall( 'unserialize', $props['metadata'] );

				$publishOptions['headers'] = $handler->getContentHeaders( $metadata );
			} else {
				$publishOptions['headers'] = [];
			}
			$archive = $repoFile->publish( $file->getPathname(), $flags, $publishOptions );
			if ( !$archive->isGood() ) {
				$this->output( "failed. (" .
					$archive->getMessage( false, false, 'en' )->text() .
					")\n" );
			}
		}

		$commentText = SpecialUpload::getInitialPageText( $commentText, '' );
		$summary = $this->getOption( 'summary', '' );
		$user = User::newSystemUser( 'Maintenance script', [ 'steal' => true ] );

		if ( $this->hasOption( 'dry' ) ) {
			$this->output( "done.\n" );
		} elseif ( $repoFile->recordUpload3( $archive->value, $summary, $commentText, $user, $props ) ) {
			$this->output( "done.\n" );
		}

		if ( $this->hasOption( 'verbose' ) ) {
			$this->output( "Canonical Filename: {$repoFile->getName()}\n" );
			$this->output( "Canonical URL: {$repoFile->getCanonicalUrl()}" );
		}

		return true;
	}
}

$maintClass = ImportFiles::class;
require_once RUN_MAINTENANCE_IF_MAIN;
