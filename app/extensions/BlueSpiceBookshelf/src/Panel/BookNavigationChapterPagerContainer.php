<?php

namespace BlueSpice\Bookshelf\Panel;

use BlueSpice\Bookshelf\BookContextProviderFactory;
use BlueSpice\Bookshelf\ChapterLookup;
use BlueSpice\Bookshelf\ChapterPager;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\Literal;
use Title;
use TitleFactory;

class BookNavigationChapterPagerContainer extends Literal {

	/** @var Title */
	private $title = null;

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var bookContextProviderFactory */
	private $bookContextProviderFactory = null;

	/** @var ChapterLookup */
	private $chapterLookup = null;

	/**
	 * @param Title $title
	 * @param TitleFactory $titleFactory
	 * @param BookContextProviderFactory $bookContextProviderFactory
	 * @param ChapterLookup $chapterLookup
	 */
	public function __construct(
		Title $title, TitleFactory $titleFactory,
		BookContextProviderFactory $bookContextProviderFactory, ChapterLookup $chapterLookup
	 ) {
		parent::__construct( '', '' );

		$this->title = $title;
		$this->titleFactory = $titleFactory;
		$this->bookContextProviderFactory = $bookContextProviderFactory;
		$this->chapterLookup = $chapterLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'bs-book-navigation-chapter-pager';
	}

	/**
	 * Raw HTML string
	 *
	 * @return string
	 */
	public function getHtml(): string {
		/** @var ChapterPager */
		$chapterPager = new ChapterPager(
			$this->titleFactory, $this->bookContextProviderFactory, $this->chapterLookup
		);

		return $chapterPager->getPagerToolbar( $this->title );
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getRequiredRLStyles(): array {
		return [ 'ext.bluespice.bookshelf.chapter-pager.styles' ];
	}

}
