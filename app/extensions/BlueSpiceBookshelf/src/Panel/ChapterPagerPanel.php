<?php

namespace BlueSpice\Bookshelf\Panel;

use BlueSpice\Bookshelf\BookContextProviderFactory;
use BlueSpice\Bookshelf\BookLookup;
use BlueSpice\Bookshelf\ChapterLookup;
use BlueSpice\Bookshelf\ChapterPager;
use Html;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\Literal;
use Title;
use TitleFactory;

class ChapterPagerPanel extends Literal {

	/** @var Title */
	protected $title = null;

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var BookContextProviderFactory */
	private $bookContextProviderFactory = null;

	/** @var BookLookup */
	private $bookLookup = null;

	/** @var ChapterLookup */
	private $chapterLookup = null;

	/** @var string */
	private $id = '';

	/**
	 * @param Title $title
	 * @param TitleFactory $titleFactory
	 * @param BookContextProviderFactory $bookContextProviderFactory
	 * @param BookLookup $bookLookup
	 * @param ChapterLookup $chapterLookup
	 * @param string $id
	 */
	public function __construct(
		Title $title, TitleFactory $titleFactory, BookContextProviderFactory $bookContextProviderFactory,
		BookLookup $bookLookup, ChapterLookup $chapterLookup, string $id
	) {
		$this->title = $title;
		$this->titleFactory = $titleFactory;
		$this->bookLookup = $bookLookup;
		$this->bookContextProviderFactory = $bookContextProviderFactory;
		$this->chapterLookup = $chapterLookup;
		$this->id = $id;

		parent::__construct( 'bs-book-chapterpager', '' );
	}

	/**
	 *
	 * @return string
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 *
	 * @return string
	 */
	public function getHtml(): string {
		/** @var ChapterPager */
		$chapterPager = new ChapterPager(
			$this->titleFactory, $this->bookContextProviderFactory, $this->chapterLookup
		);

		$html = Html::openElement( 'div', [ 'class' => 'bs-bookshelfui-chapter-pager-default-pnl' ] );
		$html .= $chapterPager->getDefaultPagerHtml( $this->title );
		$html .= Html::closeElement( 'div' );

		return $html;
	}

	/**
	 *
	 * @param IContextSource $context
	 * @return bool
	 */
	public function shouldRender( $context ): bool {
		$title = $context->getTitle();
		if ( $title->isRedirect() ) {
			$webRequestValues = $context->getRequest()->getValues();
			if ( !isset( $webRequestValues['redirect'] ) || $webRequestValues['redirect'] !== 'no' ) {
				$title = $context->getWikiPage()->getRedirectTarget();
			}
		}
		if ( empty( $this->bookLookup->getBooksForPage( $title ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getRequiredRLStyles(): array {
		return [
			'ext.bluespice.bookshelf.chapter-pager.styles'
		];
	}
}
