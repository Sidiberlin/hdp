<?php

namespace BlueSpice\Bookshelf\Tag;

use BlueSpice\Bookshelf\BookContextProviderFactory;
use BlueSpice\Bookshelf\BookLookup;
use BlueSpice\Bookshelf\Panel\BookNavigationTreeContainer;
use BlueSpice\Bookshelf\Renderer\ComponentRenderer;
use BlueSpice\Tag\Handler;
use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\CommonUserInterface\TreeDataGenerator;

class BookNavHandler extends Handler {

	public const ATTR_BOOK = 'book';
	public const ATTR_CHAPTER = 'chapter';

	/** @var string */
	private $bookInput = '';

	/** @var string */
	private $chapterInput = '';

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var ComponentRenderer */
	private $componentRenderer = null;

	/** @var BookContextProviderFactory */
	private $bookContxtProviderFactory = null;

	/** @var BookLookup */
	private $bookLookup = null;

	/** @var TreeDataGenerator */
	private $treeDataGenerator = null;

	/**
	 * @param string $processedInput
	 * @param array $processedArgs
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param TitleFactory $titleFactory
	 * @param ComponentRenderer $componentRenderer
	 * @param BookContextProviderFactory $bookContxtProviderFactory
	 * @param BookLookup $bookLookup
	 * @param TreeDataGenerator $treeDataGenerator
	 */
	public function __construct(
		$processedInput, array $processedArgs, Parser $parser,
		PPFrame $frame, TitleFactory $titleFactory, ComponentRenderer $componentRenderer,
		BookContextProviderFactory $bookContxtProviderFactory,
		BookLookup $bookLookup, TreeDataGenerator $treeDataGenerator
	 ) {
		parent::__construct( $processedInput, $processedArgs, $parser, $frame );
		$this->bookInput = $this->processedArgs[self::ATTR_BOOK];
		$this->chapterInput = $this->processedArgs[self::ATTR_CHAPTER];
		$this->titleFactory = $titleFactory;
		$this->componentRenderer = $componentRenderer;
		$this->bookContxtProviderFactory = $bookContxtProviderFactory;
		$this->bookLookup = $bookLookup;
		$this->treeDataGenerator = $treeDataGenerator;
	}

	/**
	 * @return string $bookNav
	 */
	public function handle(): string {
		$bookTitle = $this->titleFactory->newFromText( $this->bookInput, NS_BOOK );

		if ( !$bookTitle || !$bookTitle->exists() ) {
			return Message::newFromKey( 'bs-bookshelf-booknav-book-not-exist', $this->bookInput )->plain();
		}

		$bookNavTreeContainer = new BookNavigationTreeContainer(
			$bookTitle,
			$this->titleFactory,
			$this->bookContxtProviderFactory,
			$this->bookLookup,
			$this->treeDataGenerator,
			$bookTitle,
			'ct-booknav-'
		);
		$subComponents = $bookNavTreeContainer->getSubComponents();
		$html = '';
		if ( $this->chapterInput === '' ) {
			$this->buildSubComponentsHtml( $subComponents, $html );
		} else {
			$this->buildSubComponentsSegmentHtml( $subComponents, $html );
		}

		$bookNav = Html::openElement( 'div', [
			'style' => 'display: flex;'
		] );
		$bookNav .= Html::element( 'h2', [
			'style' => 'width: 80%; margin: 0; padding: 0;'
		], $bookTitle->getText()
		);
		$bookNav .= $this->buildSearchBox();
		$bookNav .= Html::closeElement( 'div' );

		$bookNav .= Html::openElement( 'ul', [
			'class' => 'mws-tree root',
			'role' => 'tree',
			'tabindex' => '0'
		] );

		$bookNav .= $html;
		$bookNav .= Html::closeElement( 'ul' );

		$parserOutput = $this->parser->getOutput();
		$parserOutput->addModules( [
			'ext.bluespice.bookshelf.bookNavFilter',
			'mwstake.component.commonui.tree-component'
		] );
		$parserOutput->addModuleStyles( [ 'ext.bluespice.bookshelf.booknav.styles' ] );

		return $bookNav;
	}

	/**
	 * @return string $searchBox
	 */
	private function buildSearchBox(): string {
		$searchBox = Html::openElement( 'div', [
			'data-selector' => '.bs-tag-booknav .mws-tree-item',
			'style' => 'width: 20%;'
		] );
		$searchBox .= Html::openElement( 'div', [
			'id' => 'ooui-php-1',
			// phpcs:ignore Generic.Files.LineLength.TooLong
			'class' => 'container-filter-search oo-ui-widget oo-ui-widget-enabled oo-ui-inputWidget oo-ui-iconElement oo-ui-textInputWidget oo-ui-textInputWidget-type-search oo-ui-textInputWidget-php',
			// phpcs:ignore Generic.Files.LineLength.TooLong
			'data-ooui' => '{"_":"OO.ui.SearchInputWidget","type":"search","icon":"search","required":false,"classes":["container-filter-search"]}'
		] );
		$searchBox .= Html::element( 'input', [
			'type' => 'search',
			'tabindex' => '0',
			'value' => '',
			'placeholder' => Message::newFromKey( 'bs-bookshelf-booknav-searchbox-placeholder' )->text(),
			'class' => 'oo-ui-inputWidget-input'
		] );
		$searchBox .= Html::element( 'span', [
			'class' => 'oo-ui-iconElement-icon oo-ui-icon-search'
		] );
		$searchBox .= Html::element( 'span', [
			'class' => 'oo-ui-indicatorElement-indicator oo-ui-indicatorElement-noIndicator'
		] );
		$searchBox .= Html::closeElement( 'div' );
		$searchBox .= Html::closeElement( 'div' );

		return $searchBox;
	}

	/**
	 * @param array $subComponents
	 * @param string &$html
	 * @return array
	 */
	private function buildSubComponentsHtml( array $subComponents, string &$html ): void {
		foreach ( $subComponents as $subComponent ) {
			$html .= $this->componentRenderer->getComponentHtml( $subComponent );
		}
	}

	/**
	 * @param array $subComponents
	 * @param string &$html
	 * @return array
	 */
	private function buildSubComponentsSegmentHtml( array $subComponents, string &$html ): void {
		foreach ( $subComponents as $subComponent ) {
			$label = $subComponent->getText()->text();

			if ( strpos( $label, $this->chapterInput ) === 0 ) {
				$html .= $this->componentRenderer->getComponentHtml( $subComponent );
			}
		}
	}
}
