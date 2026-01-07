<?php

namespace BlueSpice\Bookshelf\HookHandler;

use BlueSpice\Bookshelf\BookContextProviderFactory;
use BlueSpice\Bookshelf\BookLookup;
use BlueSpice\Bookshelf\ChapterInfo;
use BlueSpice\Bookshelf\ChapterLookup;
use BlueSpice\Bookshelf\NumberHeadings;
use BlueSpice\Bookshelf\NumberTOC;
use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Content\Content;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\Parsoid\PageBundleParserOutputConverter;
use MediaWiki\Title\Title;
use Skin;
use TextContent;

class AddChapterNumberToTitleAndHeadings {

	public const ALREADY_PROCESSED = 'bluespicebookshelf-already-processed';

	/** @var Config */
	private $config;

	/** @var BookContextProviderFactory */
	private $bookContextProviderFactory = null;

	/** @var ChapterLookup */
	private $bookChapterLookup = null;

	/** @var BookLookup */
	private $bookLookup = null;

	/** @var Title */
	private $activeBook = null;

	/**
	 * @param ConfigFactory $configFactory
	 * @param BookContextProviderFactory $bookContextProviderFactory
	 * @param ChapterLookup $bookChapterLookup
	 */
	public function __construct(
		ConfigFactory $configFactory, BookContextProviderFactory $bookContextProviderFactory,
		ChapterLookup $bookChapterLookup, BookLookup $bookLookup
	) {
		$this->config = $configFactory->makeConfig( 'bsg' );
		$this->bookContextProviderFactory = $bookContextProviderFactory;
		$this->bookChapterLookup = $bookChapterLookup;
		$this->bookLookup = $bookLookup;
	}

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return void
	 */
	public function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		if ( !$out->getTitle() ) {
			return true;
		}
		$activeBook = $this->getActiveBook( $out->getTitle() );
		if ( !$activeBook ) {
			return true;
		}
		$bookID = $this->bookLookup->getBookId( $activeBook );

		$chapterInfo = $this->getChapterInfo( $out->getTitle(), $activeBook );
		if ( $chapterInfo instanceof ChapterInfo === false ) {
			return true;
		}
		$number = $chapterInfo->getNumber();

		$out->addJsConfigVars( 'bsActiveBookId', $bookID );
		$out->addJsConfigVars( 'bsActiveChapterNumber', $number );
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
			$skip = true;
		}
		return true;
	}

	/**
	 * @param Content $content
	 * @param Title $title
	 * @param ParserOutput &$output
	 * @return void
	 */
	public function onContentAlterParserOutput( Content $content, Title $title, ParserOutput &$output ) {
		if ( $this->config->get( 'BookshelfPrependPageTOCNumbers' ) === false ) {
			return true;
		}

		if ( !$output->hasText() ) {
			return true;
		}
		if ( !( $content instanceof TextContent ) ) {
			return true;
		}

		if ( $output->getExtensionData( PageBundleParserOutputConverter::PARSOID_PAGE_BUNDLE_KEY ) !== null ) {
			return true;
		}

		if ( $output->getExtensionData( self::ALREADY_PROCESSED ) !== null ) {
			return true;
		}

		if ( !$title ) {
			return true;
		}

		$activeBook = $this->getActiveBook( $title );
		if ( !$activeBook ) {
			return true;
		}
		$chapterInfo = $this->getChapterInfo( $title, $activeBook );
		if ( $chapterInfo instanceof ChapterInfo === false ) {
			return true;
		}

		$this->setChapterNumberInFirstHeading( $activeBook, $chapterInfo, $output );
		$this->setChapterNumberInContent( $activeBook, $chapterInfo, $output );
		$output->setExtensionData( self::ALREADY_PROCESSED, true );

		return true;
	}

	/**
	 * @param Title $activeBook
	 * @param ChapterInfo $chapterInfo
	 * @param ParserOutput $output
	 * @return void
	 */
	private function setChapterNumberInFirstHeading(
		Title $activeBook, ChapterInfo $chapterInfo, ParserOutput $output
	) {
		$number = $chapterInfo->getNumber();
		$output->setTitleText( "<span class='bs-chapter-number'>$number</span> {$chapterInfo->getName()}" );
	}

	/**
	 * @param Title $activeBook
	 * @param ChapterInfo $chapterInfo
	 * @param ParserOutput $output
	 * @return void
	 */
	private function setChapterNumberInContent(
		Title $activeBook, ChapterInfo $chapterInfo, ParserOutput $output
	) {
		$text = $output->getText();

		$numberToc = new NumberTOC();
		$text = $numberToc->execute(
			$chapterInfo->getNumber(),
			$text
		);

		$numberHeadings = new NumberHeadings();
		$text = $numberHeadings->execute(
			$chapterInfo->getNumber(),
			$text
		);

		$children = $this->bookChapterLookup->getChildren( $this->activeBook, $chapterInfo );
		if ( !empty( $children ) ) {
			// Otherwise the internal headlines would have same numbers as child node articles
			$text = $this->hideHeadingNumberInToc( $text );
			$text = $this->hideChapterNumberInContent( $text );
			$text = $this->hideHeadingNumberInContent( $text );
		}

		$output->setText( $text );
	}

	/**
	 * @param string $html
	 * @return string
	 */
	private function hideHeadingNumberInToc( $html ) {
		$regEx = '#(<span class="tocnumber">)([\d\.]*?\s*?</span>)#';

		$matches = [];
		$status = preg_match_all( $regEx, $html, $matches );
		if ( !$status ) {
			return $html;
		}

		foreach ( $matches[0] as $index => $match ) {
			$replacement = '<span class="tocnumber hidden">' . $matches[2][$index];
			$pattern = '#' . preg_quote( $match, '#' ) . '#';
			$html = preg_replace(
				$pattern,
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
	private function hideChapterNumberInContent( $html ) {
		$regEx = '#(<span class="bs-chapter-number">)([\d\.]*?\s*?</span>)#';

		$matches = [];
		$status = preg_match_all( $regEx, $html, $matches );
		if ( !$status ) {
			return $html;
		}

		foreach ( $matches[0] as $index => $match ) {
			$replacement = '<span class="bs-chapter-number hidden">' . $matches[2][$index];
			$pattern = '#' . preg_quote( $match, '#' ) . '#';
			$html = preg_replace(
				$pattern,
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
	private function hideHeadingNumberInContent( $html ) {
		$regEx = '#(<span class="mw-headline-number">)([\d\.]*?\s*?</span>)#';

		$matches = [];
		$status = preg_match_all( $regEx, $html, $matches );
		if ( !$status ) {
			return $html;
		}

		foreach ( $matches[0] as $index => $match ) {
			$replacement = '<span class="mw-headline-number hidden">' . $matches[2][$index];
			$pattern = '#' . preg_quote( $match, '#' ) . '#';
			$html = preg_replace(
				$pattern,
				$replacement,
				$html
			);
		}

		return $html;
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
