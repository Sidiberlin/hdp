<?php

namespace BlueSpice\Bookshelf\HookHandler;

use BlueSpice\Bookshelf\BookContextProviderFactory;
use BlueSpice\Bookshelf\ChapterInfo;
use BlueSpice\Bookshelf\ChapterLookup;
use BlueSpice\Bookshelf\HeadingNumberation;
use BlueSpice\Bookshelf\TOCNumberation;
use Config;
use ConfigFactory;
use OutputPage;
use Skin;
use Title;

class AddChapterNumberToTitleAndHeadings {

	/** @var Config */
	private $config;

	/** @var BookContextProviderFactory */
	private $bookContextProviderFactory = null;

	/** @var ChapterLookup */
	private $bookChapterLookup = null;

	/** @var Title */
	private $activeBook = null;

	/**
	 * @param ConfigFactory $configFactory
	 * @param BookContextProviderFactory $bookContextProviderFactory
	 * @param ChapterLookup $bookChapterLookup
	 */
	public function __construct(
		ConfigFactory $configFactory, BookContextProviderFactory $bookContextProviderFactory,
		ChapterLookup $bookChapterLookup
	) {
		$this->config = $configFactory->makeConfig( 'bsg' );
		$this->bookContextProviderFactory = $bookContextProviderFactory;
		$this->bookChapterLookup = $bookChapterLookup;
	}

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return bool
	 */
	public function onBeforePageDisplay( $out, $skin ) {
		$title = $out->getTitle();
		if ( !$title ) {
			return true;
		}

		$activeBook = $this->getActiveBook( $out->getTitle() );
		if ( !$activeBook ) {
			return true;
		}
		$chapterInfo = $this->getChapterInfo( $title, $activeBook );
		if ( $chapterInfo instanceof ChapterInfo === false ) {
			return true;
		}

		$displayTitle = $out->getPageTitle();
		// If a title text is set in the book source it should be used instead of title
		// and even instead of DISPLAYTITLE
		if ( $this->config->get( 'BookshelfTitleDisplayText' ) ) {
			$displayTitle = $chapterInfo->getName();
		}

		$number = $chapterInfo->getNumber();

		$out->setPageTitle( "<span class='bs-chapter-number'>$number</span> $displayTitle" );

		return true;
	}

	/**
	 * @param OutputPage $out
	 * @param string &$text
	 * @return bool
	 */
	public function onOutputPageBeforeHTML( OutputPage $out, &$text ) {
		if ( $this->config->get( 'BookshelfPrependPageTOCNumbers' ) === false ) {
			return true;
		}

		$activeBook = $this->getActiveBook( $out->getTitle() );
		if ( !$activeBook ) {
			return true;
		}
		$chapterInfo = $this->getChapterInfo( $out->getTitle(), $activeBook );
		if ( $chapterInfo instanceof ChapterInfo === false ) {
			return true;
		}

		$children = $this->bookChapterLookup->getChildren( $this->activeBook, $chapterInfo );
		if ( !empty( $children ) ) {
			// Otherwise the internal headlines would have same numbers as child node articles
			$text = $this->removeHeadingNumberFromToc( $text );
			$text = $this->removeHeadingNumberFromHeading( $text );
			return true;
		}
		$headingNumberation = new HeadingNumberation();
		$text = $headingNumberation->execute(
			$chapterInfo->getNumber(),
			$text
		);

		$tocNumberation = new TOCNumberation();
		$text = $tocNumberation->execute(
			$chapterInfo->getNumber(),
			$text
		);

		return true;
	}

	/**
	 * @param string $html
	 * @return string
	 */
	private function removeHeadingNumberFromToc( $html ) {
		$regEx = '#(<span class="tocnumber">)([\d\.]*?\s*?</span>)#';

		$matches = [];
		$status = preg_match_all( $regEx, $html, $matches );
		if ( !$status ) {
			return $html;
		}

		for ( $index = 0; $index < count( $matches[0] ); $index++ ) {
			$replacement = '<span class="tocnumber hidden">' . $matches[2][$index];
			$html = preg_replace(
				'#' . $matches[0][$index] . '#',
				$replacement,
				$html
			);
		}

		return $html;
	}

	/**
	 * @param string $html
	 * @return string
	 */
	private function removeHeadingNumberFromHeading( $html ) {
		$regEx = '#(<span class="mw-headline-number">)([\d\.]*?\s*?</span>)#';

		$matches = [];
		$status = preg_match_all( $regEx, $html, $matches );
		if ( !$status ) {
			return $html;
		}

		for ( $index = 0; $index < count( $matches[0] ); $index++ ) {
			$replacement = '<span class="mw-headline-number hidden">' . $matches[2][$index];
			$html = preg_replace(
				'#' . $matches[0][$index] . '#',
				$replacement,
				$html
			);
		}

		return $html;
	}

	/**
	 * @param bool &$skip
	 * @param string &$prefix
	 * @param Title $title
	 * @param string $html
	 * @return bool
	 */
	public function onNumberHeadingsBeforeApply( &$skip, &$prefix, $title, $html ) {
		$activeBook = $this->getActiveBook( $title );
		if ( !$activeBook ) {
			return true;
		}
		$chapterInfo = $this->getChapterInfo( $title, $activeBook );
		if ( $chapterInfo instanceof ChapterInfo === false ) {
			return true;
		}
		if ( $this->config->get( 'BookshelfPrependPageTOCNumbers' ) === true ) {
			$children = $this->bookChapterLookup->getChildren( $activeBook, $chapterInfo );
			if ( empty( $children ) ) {
				// Skip only if chapter has no children
				$skip = true;
			}
		}
		return true;
	}

	/**
	 * @param Title $title
	 * @return Title|null
	 */
	private function getActiveBook( Title $title ): ?Title {
		if ( $this->activeBook === null ) {
			$bookContextProvider = $this->bookContextProviderFactory->getProvider( $title );
			$this->activeBook = $bookContextProvider->getActiveBook();
		}
		return $this->activeBook;
	}

	/**
	 * @param Title $title
	 * @param Title $activeBook
	 * @return ChapterInfo|null
	 */
	private function getChapterInfo( Title $title, Title $activeBook ): ?ChapterInfo {
		$chapterInfo = $this->bookChapterLookup->getChapterInfoFor( $activeBook, $title );

		return $chapterInfo;
	}
}
