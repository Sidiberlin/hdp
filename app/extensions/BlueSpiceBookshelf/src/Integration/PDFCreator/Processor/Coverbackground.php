<?php

namespace BlueSpice\Bookshelf\Integration\PDFCreator\Processor;

use BlueSpice\Bookshelf\BookMetaLookup;
use DOMElement;
use DOMXPath;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Extension\PDFCreator\IProcessor;
use MediaWiki\Extension\PDFCreator\PDFCreator;
use MediaWiki\Extension\PDFCreator\Utility\ExportContext;
use MediaWiki\Extension\PDFCreator\Utility\UncollideFilename;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\TitleFactory;
use RepoGroup;

class Coverbackground implements IProcessor {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var BookMetaLookup */
	private $bookMetaLookup;

	/** @var RepoGroup */
	private $repoGroup;

	/** @var ConfigFactory */
	private $configFactory;

	/**
	 *
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		TitleFactory $titleFactory, BookMetaLookup $bookMetaLookup,
		RepoGroup $repoGroup, ConfigFactory $configFactory
	) {
		$this->titleFactory = $titleFactory;
		$this->bookMetaLookup = $bookMetaLookup;
		$this->repoGroup = $repoGroup;
		$this->configFactory = $configFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function execute( array &$pages, array &$images, array &$attachments,
		ExportContext $context, string $module = '', $params = []
	): void {
		if ( $module !== 'book' ) {
			return;
		}
		if ( empty( $pages ) ) {
			return;
		}
		// Intro page is first page in pages array if a intro page is used
		/** @var ExportPage */
		$page = $pages[0];
		if ( $page->getType() !== PDFCreator::INTRO ) {
			return;
		}
		$bookIdentity = $context->getPageIdentity();
		if ( !$bookIdentity instanceof PageIdentity ) {
			return;
		}
		$bookTitle = $this->titleFactory->newFromPageIdentity( $bookIdentity );
		if ( !$bookTitle->exists() ) {
			return;
		}
		$bookshelfImage = $this->bookMetaLookup->getMetaValueForBook( $bookTitle, 'bookshelfimage' );
		if ( $bookshelfImage === '' ) {
			return;
		}

		$filename = '';
		$absFileSystemPath = '';

		$fileTitle = $this->titleFactory->newFromText( $bookshelfImage );
		if ( $fileTitle->getNamespace() !== NS_FILE ) {
			$fileTitle = $this->titleFactory->makeTitle( NS_FILE, $bookshelfImage );
		}
		if ( $fileTitle->exists() ) {
			$file = $this->repoGroup->findFile( $fileTitle );
			if ( $file && $file->getLocalRefPath() ) {
				$filename = $file->getName();
				$absFileSystemPath = $file->getLocalRefPath();
			}
		} else {
			// Variable does not contain file page
			if ( file_exists( $bookshelfImage ) ) {
				$last = strrpos( $bookshelfImage, DIRECTORY_SEPARATOR );
				$filename = substr( $bookshelfImage, $last + 1 );
				$absFileSystemPath = $bookshelfImage;
			}
		}

		if ( $filename === '' || $absFileSystemPath === '' ) {
			return;
		}

		$uncollideFilename = new UncollideFilename();
		$filename = $uncollideFilename->execute( $filename, $absFileSystemPath, $images );
		if ( !isset( $images[$filename] ) ) {
			$images[$filename] = $absFileSystemPath;
		}

		// Add background url in dom
		$dom = $page->getDOMDocument();
		$xpath = new DOMXPath( $dom );
		$introElements = $xpath->query(
			'//div[contains(@class, "pdfcreator-type-intro")]',
			$dom
		);
		if ( !$introElements || $introElements->count() === 0 ) {
			return;
		}

		$introElement = $introElements->item( 0 );
		if ( $introElement instanceof DOMElement === false ) {
			return;
		}

		$matches = [];
		$hasMatches = false;
		$style = '';
		if ( $introElement->hasAttribute( 'style' ) ) {
			$style = $introElement->getAttribute( 'style' );
			$hasMatches = preg_match( '/background-image:\s*url\((.*?)\)/', $style, $matches );
		}

		if ( !$hasMatches ) {
			$style .= ' background-image: url(\'images/' . $filename . '\');';
		} else {
			$newStyle = preg_replace(
				'/background-image:\s*url\((.*?)\)/',
				'background-image: url("images/' . $filename . '");',
				$style
			);
			if ( is_string( $newStyle ) ) {
				$style = $newStyle;
			}
		}

		$introElement->setAttribute( 'style', trim( $style ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getPosition(): int {
		return 11;
	}
}
