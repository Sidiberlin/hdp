<?php

namespace BlueSpice\Bookshelf\Panel;

use BlueSpice\Bookshelf\BookContextProviderFactory;
use BlueSpice\Bookshelf\BookLookup;
use BlueSpice\Bookshelf\ChapterLookup;
use Html;
use IContextSource;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\ComponentBase;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\Literal;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleCard;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleCardFooter;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleCardHeader;
use MWStake\MediaWiki\Component\CommonUserInterface\ITabPanel;
use MWStake\MediaWiki\Component\CommonUserInterface\TreeDataGenerator;
use Title;
use TitleFactory;

class SidebarBookPanel extends ComponentBase implements ITabPanel {

	/** @var Title */
	protected $title;

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var BookContextProviderFactory */
	private $bookContextProviderFactory = null;

	/** @var BookLookup */
	private $bookLookup = null;

	/** @var ChapterLookup */
	private $chapterLookup = null;

	/** @var TreeDataGenerator */
	private $treeDataGenerator = null;

	/**
	 * @param Title $title
	 * @param TitleFactory $titleFactory
	 * @param BookContextProviderFactory $bookContextProviderFactory
	 * @param BookLookup $bookLookup
	 * @param ChapterLookup $chapterLookup
	 * @param TreeDataGenerator $treeDataGenerator
	 */
	public function __construct(
		Title $title, TitleFactory $titleFactory, BookContextProviderFactory $bookContextProviderFactory,
		BookLookup $bookLookup, ChapterLookup $chapterLookup, TreeDataGenerator $treeDataGenerator
	) {
		$this->title = $title;
		$this->titleFactory = $titleFactory;
		$this->bookContextProviderFactory = $bookContextProviderFactory;
		$this->bookLookup = $bookLookup;
		$this->chapterLookup = $chapterLookup;
		$this->treeDataGenerator = $treeDataGenerator;
	}

	/**
	 *
	 * @return string
	 */
	public function getId(): string {
		return 'book-navigation-panel';
	}

	/**
	 * @inheritDoc
	 */
	public function getContainerClasses(): array {
		return [ 'book-nav-panel' ];
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getRequiredRLStyles(): array {
		return [
			'ext.bookshelf.navigation-panel.styles'
		];
	}

	/**
	 *
	 * @return Message
	 */
	public function getText(): Message {
		return Message::newFromKey( 'bs-bookshelfui-panel-navigation-text' );
	}

	/**
	 *
	 * @return Message
	 */
	public function getTitle(): Message {
		return Message::newFromKey( 'bs-bookshelfui-panel-navigation-title' );
	}

	/**
	 *
	 * @return Message
	 */
	public function getAriaLabel(): Message {
		return Message::newFromKey( 'bs-bookshelfui-panel-navigation-aria-label' );
	}

	/**
	 *
	 * @return Message
	 */
	public function getAriaDesc(): Message {
		return Message::newFromKey( 'bs-bookshelfui-panel-navigation-aria-desc' );
	}

	/**
	 *
	 * @return IComponent[]
	 */
	public function getSubComponents(): array {
		$bookContextProvider = $this->bookContextProviderFactory->getProvider( $this->title );
		$activeBook = $bookContextProvider->getActiveBook();

		if ( $activeBook instanceof Title === false ) {
			return [];
		}

		$items = [];

		$allBooks = $this->bookLookup->getBooksForPage( $this->title );
		if ( count( $allBooks ) > 1 ) {
			$items[] = new BookSelectWidget( [
					'id' => 'book-nav-pri-book-selector',
					'container-classes' => [],
					'button-classes' => [],
					'menu-classes' => []
				],
				$activeBook,
				$this->title,
				$this->bookLookup,
				$this->titleFactory
			);
		}

		$items[] = new SimpleCard( [
			'id' => 'n-book-panel',
			'classes' => [ 'w-100', 'bg-transp' ],
			'items' => [
				new SimpleCardHeader( [
					'id' => 'n-book-panel-header',
					'classes' => [ 'bg-transp' ],
					'items' => [
						new Literal(
							'n-book-panel-header-text',
							'<span class="book-title">' . $this->getBookTitle( $activeBook ) . '</span>'
						)
					]
				] ),
				new BookNavigationChapterPagerContainer(
					$this->title, $this->titleFactory, $this->bookContextProviderFactory, $this->chapterLookup
				),
				new BookNavigationTreeContainer(
					$this->title, $this->titleFactory, $this->bookContextProviderFactory,
					$this->bookLookup, $this->treeDataGenerator
				),
				new SimpleCardFooter( [
					'id' => 'n-book-panel-footer',
					'classes' => [ 'bg-transp' ],
						'items' => [
							new Literal(
								'n-book-panel-footer-text',
								$this->getBookEditLink( $activeBook )
							)
						]
				] )
			]
		] );

		return $items;
	}

	/**
	 *
	 * @param IContextSource $context
	 * @return bool
	 */
	public function shouldRender( IContextSource $context ): bool {
		if ( $this->title->isRedirect() ) {
			$webRequestValues = $context->getRequest()->getValues();
			if ( !isset( $webRequestValues['redirect'] ) || $webRequestValues['redirect'] !== 'no' ) {
				$redirTarget = MediaWikiServices::getInstance()->getRedirectLookup()
					->getRedirectTarget( $context->getWikiPage() );
				if ( $redirTarget instanceof LinkTarget ) {
					$this->title = Title::newFromLinkTarget( $redirTarget );
				}
			}
		}

		$provider = $this->bookContextProviderFactory->getProvider( $this->title );

		if ( $provider->getActiveBook() === null ) {
			return false;
		}

		return true;
	}

	/**
	 *
	 * @param IContextSource $context
	 * @return bool
	 */
	public function isActive( $context ): bool {
		return $this->shouldRender( $context );
	}

	/**
	 * @param Title|null $activeBook
	 * @return string
	 */
	private function getBookTitle( $activeBook ): string {
		if ( $activeBook instanceof Title ) {
			return $activeBook->getText();
		}

		return '';
	}

	/**
	 * @param Title|null $activeBook
	 * @return string
	 */
	protected function getBookEditLink( $activeBook ): string {
		if ( $activeBook instanceof Title === false ) {
			return '';
		}

		$bookEditorLink = Html::openElement(
			'a',
			[
				'id' => 'book-panel-edit-book',
				'href' => $activeBook->getFullURL( [ 'action' => 'edit' ] ),
				'title' => wfMessage( 'bs-bookshelfui-book-title-link-edit' )->plain()
			]
		);
		$bookEditorLink .=
			wfMessage( 'bs-bookshelfui-book-title-link-edit-text' )->plain();

		$bookEditorLink .= Html::closeElement( 'a' );

		return $bookEditorLink;
	}
}
