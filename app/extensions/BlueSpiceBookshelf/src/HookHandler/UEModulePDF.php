<?php

namespace BlueSpice\Bookshelf\HookHandler;

use BlueSpice\Bookshelf\BookContextProviderFactory;
use BlueSpice\Bookshelf\BookLookup;
use BlueSpice\Bookshelf\ChapterLookup;
use DOMElement;
use DOMXPath;
use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use PDFFileResolver;
use RepoGroup;

class UEModulePDF {

	/** @var BookContextProviderFactory */
	private $bookContextProviderFactory = null;

	/** @var BookLookup */
	private $bookLookup = null;

	/** @var ChapterLookup */
	private $bookChapterLookup = null;

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var Config */
	private $config = null;

	/** @var Config */
	private $mainConfig = null;

	/** @var RepoGroup */
	private $repoGroup = null;

	/** @var HookContainer */
	private $hookContainer = null;

	/**
	 * @param BookContextProviderFactory $bookContextProviderFactory
	 * @param BookLookup $bookLookup
	 * @param ChapterLookup $bookChapterLookup
	 * @param TitleFactory $titleFactory
	 * @param ConfigFactory $configFactory
	 * @param Config $mainConfig
	 * @param RepoGroup $repoGroup
	 * @param HookContainer $hookContainer
	 */
	public function __construct(
		BookContextProviderFactory $bookContextProviderFactory,
		BookLookup $bookLookup, ChapterLookup $bookChapterLookup,
		TitleFactory $titleFactory, ConfigFactory $configFactory, Config $mainConfig,
		RepoGroup $repoGroup, HookContainer $hookContainer
	 ) {
		$this->bookContextProviderFactory = $bookContextProviderFactory;
		$this->bookLookup = $bookLookup;
		$this->bookChapterLookup = $bookChapterLookup;
		$this->titleFactory = $titleFactory;
		$this->config = $configFactory->makeConfig( 'bsg' );
		$this->mainConfig = $mainConfig;
		$this->repoGroup = $repoGroup;
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @param Title $title
	 * @param array &$page
	 * @param array &$params
	 * @param DOMXPath $DOMXPath
	 */
	public function onBSUEModulePDFgetPage( $title, &$page, &$params, $DOMXPath ) {
		$bookContextProvider = $this->bookContextProviderFactory->getProvider( $title );
		$activeBook = $bookContextProvider->getActiveBook();
		if ( !$activeBook ) {
			return;
		}

		$ancestors = $this->getAncestorsFor( $activeBook, $title );
		if ( empty( $ancestors ) ) {
			return;
		}

		$bookInfo = $this->bookLookup->getBookInfo( $activeBook );
		if ( $bookInfo === null ) {
			return;
		}

		$bookName = $bookInfo->getName();

		$this->createRunningHeader( $bookName, $ancestors, $page );
	}

	/**
	 * @param Title $activeBook
	 * @param Title $title
	 * @return array
	 */
	private function getAncestorsFor( Title $activeBook, Title $title ): array {
		$chapterInfo = $this->bookChapterLookup->getChapterInfoFor( $activeBook, $title );

		if ( !$chapterInfo ) {
			return [];
		}

		$number = $chapterInfo->getNumber();

		$chapters = $this->bookChapterLookup->getChaptersOfBook( $activeBook );

		$ancestors = [];
		foreach ( $chapters as $chapter ) {
			if (
				strpos( $chapter->getNumber(), $number ) === 0
				&& strlen( $chapter->getNumber() ) === strlen( $number ) + 2
			) {
				// is ancestor
				$ancestors[] = $chapter;
			}
		}

		return $ancestors;
	}

	/**
	 * @param string $bookName
	 * @param array $ancestors
	 * @param array $page
	 */
	private function createRunningHeader( string $bookName, array $ancestors, array $page ): void {
		$runningHeader = $page['dom']->createElement( 'div' );
		$runningHeader->setAttribute( 'class', 'bs-runningheader' );

		$page['bodycontent-element']->parentNode->insertBefore(
			$runningHeader, $page['bodycontent-element']
		);

		$bookTitle = $page['dom']->createElement( 'div' );
		$sourceTextNode = $page['dom']->createTextNode( $bookName );
		$bookTitle->appendChild( $sourceTextNode );
		$bookTitle->setAttribute( 'class', 'bs-booktitle' );

		$oAncestorTable = $page['dom']->createElement( 'table' );
		$oAncestorTR = $oAncestorTable->appendChild( $page['dom']->createElement( 'tr' ) );
		$runningHeader->appendChild( $oAncestorTable );

		$oAncestorTD = $oAncestorTR->appendChild( $page['dom']->createElement( 'td' ) );
		$oAncestorTD->setAttribute( 'class', 'bs-ancestors-left' );
		$oAncestorTD->appendChild( $bookTitle );

		if ( empty( $ancestors ) ) {
			// If there are no ancestors we don't need to create a second TD
			return;
		}

		$chapterAncestors = $page['dom']->createElement( 'div' );
		$chapterAncestors->setAttribute( 'class', 'bs-ancestors' );

		foreach ( $ancestors as $ancestor ) {
			$chapterAncestor = $page['dom']->createElement( 'div' );
			$chapterAncestor->setAttribute( 'class', 'bs-ancestor' );

			$numberedAncestorTitle = $ancestor->getNumber() . '. ' . $ancestor->getName();
			$ancestorElement = $page['dom']->createTextNode( $numberedAncestorTitle );

			$chapterAncestor->appendChild( $ancestorElement );
			$chapterAncestors->appendChild( $chapterAncestor );
		}

		$oAncestorTD = $oAncestorTR->appendChild( $page['dom']->createElement( 'td' ) );
		$oAncestorTD->setAttribute( 'class', 'bs-ancestors-right' );
		$oAncestorTD->appendChild( $chapterAncestors );
	}

	/**
	 * @param Title $title
	 * @param array $pageDOM
	 * @param array &$params
	 * @param DOMXPath $DOMXPath
	 * @param array &$meta
	 * @return bool
	 */
	public function onBSUEModulePDFcollectMetaData( $title, $pageDOM, &$params, $DOMXPath, &$meta ) {
		if ( $title->getNamespace() !== NS_BOOK ) {
			return true;
		}

		if ( $this->config->get( 'BookshelfSupressBookNS' ) ) {
			// Otherwise it has intentionally been overwritten and we don't want to overwrite it
			// again
			if ( $meta['title'] === $title->getPrefixedText() ) {
				$meta['title'] = $title->getText();
			}
		}
		// TODO RBV (01.02.12 14:14): Currently the bs:bookmeta tag renders a
		// div.bs-universalexport-meta. Therefore things like "subtitle" are
		// read in by BsPDFPageProvider. Not sure if this is good...

		return true;
	}

	/**
	 * @param BsPDFServlet $sender
	 * @param null $html Unused
	 * @param array &$files
	 * @param array $params
	 * @param DOMXPath $DOMXPath
	 * @return bool
	 * @throws FatalError
	 * @throws MWException
	 */
	public function onBSUEModulePDFAfterFindFiles( $sender, $html, &$files, $params, $DOMXPath ) {
		// Find all files for attaching and merging...
		if ( $params['attachments'] != '1' ) {
			return true;
		}

		$uploadPath = $this->mainConfig->get( 'UploadPath' );

		// Backwards compatibility
		if ( !empty( $files['ATTACHMENT'] ) ) {
			foreach ( $files['ATTACHMENT'] as $sFileName => $sFilePath ) {
				$files['attachments'][$sFileName] = $sFilePath;
			}
			unset( $files['ATTACHMENT'] );
		}

		// TODO RBV (08.02.11 15:15): Necessary to exclude images?
		$oFileAnchorElements = $DOMXPath->query(
			"//a[contains(@class,'internal') and not(contains(@class, 'image'))]"
		);

		foreach ( $oFileAnchorElements as $oFileAnchorElement ) {
			if ( $oFileAnchorElement instanceof DOMElement === false ) {
				continue;
			}
			$sHref = urldecode( $oFileAnchorElement->getAttribute( 'href' ) );

			$vUploadPathIndex = strpos( $sHref, $uploadPath );
			if ( $vUploadPathIndex == false ) {
				continue;
			}

			$sFileTitle = $oFileAnchorElement->getAttribute( 'data-bs-title' );
			$oTitle = $this->titleFactory->newFromText( $sFileTitle );
			if ( $oTitle && $oTitle->getNamespace() === NS_MEDIA ) {
				$oTitle = $this->titleFactory->makeTitle( NS_FILE, $oTitle->getDBkey() );
			}
			if ( $oTitle === null ) {
				// Fallback to less secure standard attribute
				$sFileTitle = $oFileAnchorElement->getAttribute( 'title' );
				$oTitle = $this->titleFactory->makeTitle( NS_FILE, $sFileTitle );
			}
			if ( $oTitle->exists() ) {
				$oFile = $this->repoGroup->findFile( $oTitle );
				$oBackend = $oFile->getRepo()->getBackend();
				$oLocalFile = $oBackend->getLocalReference(
					[ 'src' => $oFile->getPath() ]
				);
				if ( $oLocalFile === null ) {
					continue;
				}

				$fileResolver = PDFFileResolver::factory(
					$oFileAnchorElement,
					$params['webroot-filesystempath'],
					'href'
				);

				// TODO: This should be part of BSF
				$sHrefFilename = str_replace(
					[ '/', '\\', ':', '*', '?', '"', '|', '>', '<', ],
					'-',
					str_replace( ' ', '_', $oTitle->getText() )
				/*
				 * Replacing spaces with underscores is because of the
				 * new implementation of "BShtml2PDF.war"
				 * org.xhtmlrenderer.pdf.ITextOutputDevice.processLink:271ff
				 * uses the uri to build the filename _and_ to fetch the
				 * resource. But xhtmlrenderes user agent needs URI
				 * encoded spaces (%20) to get the data. But thos would
				 * also show up in the file name. A better solution would
				 * be to have a separate data-fs-embed-file-name attribute
				 * for unencoded filename
				 */
				);
				$sAbsoluteFileSystemPath = $fileResolver->getAbsoluteFilesystemPath();
			} else {
				continue;
			}

			$this->hookContainer->run(
				'BSUEModulePDFFindFiles',
				[
					$sender,
					$oFileAnchorElement,
					&$sAbsoluteFileSystemPath,
					&$sHrefFilename,
					'attachments'
				]
			);
			$oFileAnchorElement->setAttribute( 'data-fs-embed-file', 'true' );
			// https://otrs.hallowelt.biz/otrs/index.pl?Action=AgentTicketZoom;TicketID=5036#30864
			$oFileAnchorElement->setAttribute( 'href', 'attachments/' . $sHrefFilename );
			// Necessary for org.xhtmlrenderer.pdf.ITextReplacedElementFactory.
			// Otherwise not replaceable
			$sStyle = $oFileAnchorElement->getAttribute( 'style' );
			$oFileAnchorElement->setAttribute( 'style', 'display:inline-block;' . $sStyle );
			$files['attachments'][$sHrefFilename] = $sAbsoluteFileSystemPath;
		}

		return true;
	}
}
