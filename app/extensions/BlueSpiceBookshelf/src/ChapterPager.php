<?php

namespace BlueSpice\Bookshelf;

use Html;
use Title;
use TitleFactory;

class ChapterPager {
	/** @var Title */
	protected $bookTitle;

	/** @var array */
	protected $previousTitle = [];

	/** @var array */
	protected $currentTitle = [];

	/** @var array */
	protected $nextTitle = [];

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var ChapterLookup */
	private $chapterLookup = null;

	/** @var bookContextProviderFactory */
	private $bookContextProviderFactory = null;

	/**
	 * @param TitleFactory $titleFactory
	 * @param BookContextProviderFactory $bookContextProviderFactory
	 * @param ChapterLookup $chapterLookup
	 */
	public function __construct(
		TitleFactory $titleFactory, BookContextProviderFactory $bookContextProviderFactory, ChapterLookup $chapterLookup
	) {
		$this->titleFactory = $titleFactory;
		$this->chapterLookup = $chapterLookup;
		$this->bookContextProviderFactory = $bookContextProviderFactory;
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	private function makePagerData( $title ): bool {
		/** @var IBookContextProvider */
		$bookContextProvider = $this->bookContextProviderFactory->getProvider( $title );
		$this->bookTitle = $bookContextProvider->getActiveBook();

		if ( !$this->bookTitle ) {
			return false;
		}

		$chapters = $this->chapterLookup->getChaptersOfBook( $this->bookTitle );

		$chapterPages = [];
		$current = 0;
		foreach ( $chapters as $chapter ) {
			if ( $chapter->getType() === 'plain-text' ) {
				continue;
			}

			$chapterPages[] = $chapter;

			$chapterTitle = $this->titleFactory->makeTitle( $chapter->getNamespace(), $chapter->getTitle() );
			if ( $title->equals( $chapterTitle ) ) {
				$current = count( $chapterPages ) - 1;
			}
		}

		$this->currentTitle = $chapterPages[$current];
		if ( $current > 0 ) {
			/** @var ChapterDataModel */
			$chapterData = $chapterPages[$current - 1];
			$this->previousTitle = $this->makeChapterArray( $chapterData );
		}
		if ( count( $chapterPages ) - 1 > $current ) {
			/** @var ChapterDataModel */
			$chapterData = $chapterPages[$current + 1];
			$this->nextTitle = $this->makeChapterArray( $chapterData );
		}

		return true;
	}

	/**
	 * @param ChapterDataModel $chapter
	 * @return array
	 */
	private function makeChapterArray( ChapterDataModel $chapter ): array {
		return [
			'chapter_namespace' => $chapter->getNamespace(),
			'chapter_title' => $chapter->getTitle(),
			'chapter_name' => $chapter->getName(),
			'chapter_number' => $chapter->getNumber(),
			'chapter_type' => $chapter->getType(),
		];
	}

	/**
	 *
	 * @return string
	 */
	private function getBookTitle() {
		return $this->bookTitle->getText();
	}

	/**
	 *
	 * @return array
	 */
	private function getNextPageData() {
		return $this->nextTitle;
	}

	/**
	 *
	 * @return string
	 */
	private function getNextPageButton() {
		return $this->makePagerButton( $this->getNextPageData(), 'next' );
	}

	/**
	 *
	 * @return array
	 */
	private function getCurrentPageData() {
		return $this->currentTitle;
	}

	/**
	 *
	 * @return array
	 */
	private function getPreviousPageData() {
		return $this->previousTitle;
	}

	/**
	 *
	 * @return string
	 */
	private function getPreviousPageButton() {
		return $this->makePagerButton( $this->getPreviousPageData(), 'previous' );
	}

	/**
	 * @param Title $title
	 * @return string
	 */
	public function getPagerToolbar( Title $title ): string {
		$html = '';

		if ( $this->makePagerData( $title ) ) {
			$html = Html::openElement( 'div', [ 'class' => 'bs-chapter-pager-toolbar' ] );
			$html .= $this->getPreviousPageButton();
			$html .= $this->getNextPageButton();
			$html .= Html::closeElement( 'div' );
		}

		return $html;
	}

	/**
	 * @param array $pageData
	 * @param string $type
	 * @return string
	 */
	private function makePagerButton( array $pageData, string $type ): string {
		$label = '';
		if ( isset( $pageData['chapter_name'] ) ) {
			$label = $pageData['chapter_name'];
		}

		$btnTitle = null;
	   if ( isset( $pageData['chapter_namespace'] ) && isset( $pageData['chapter_title'] )
		) {
		   $btnTitle = $this->titleFactory->makeTitle(
				$pageData['chapter_namespace'],
				$pageData['chapter_title']
		   );

		   if ( $label === '' ) {
				$label = $btnTitle->getText();
		   }
	   }

	   $btnData = [];
	   if ( $btnTitle ) {
		   $btnData = [
			   'class' => "$type-chapter",
			   'href' => $btnTitle->getFullURL(),
			   'title' => $pageData['chapter_name']
		   ];
	   } else {
		   $btnData = [
			   'class' => "disabled $type-chapter",
			   'disabled' => 'true'
		   ];
	   }

	   $html = Html::openElement( 'a', $btnData );
	   /**
		* Message keys:
		* bs-bookshelfui-chapterpager-next
		* bs-bookshelfui-chapterpager-previous
		*/
	   $html .= Html::element(
			   'span',
			   [],
			   wfMessage( "bs-bookshelfui-chapterpager-$type" )->plain()
		   );
	   $html .= Html::closeElement( 'a' );

	   return $html;
	}

	/**
	 * @param Title $title
	 * @return string
	 */
	public function getDefaultPagerHtml( Title $title ) {
		$html = '';

		if ( $this->makePagerData( $title ) ) {
			$html = Html::openElement( 'div', [ 'class' => 'bs-chapter-pager-heading' ] );
			$html .= Html::element(
				'span',
				[
					'class' => 'book-title'
				],
				$this->getBookTitle()
			);
			$html .= Html::closeElement( 'div' );
			$html .= $this->getPagerToolbar( $title );
		}

		return $html;
	}

}
