<?php

namespace BlueSpice\Bookshelf\HookHandler;

use BlueSpice\Bookshelf\BookContextProviderFactory;
use BlueSpice\Bookshelf\BookLookup;
use BlueSpice\Bookshelf\ChapterLookup;
use BlueSpice\Bookshelf\Panel\ChapterPagerPanel;
use BlueSpice\Bookshelf\Panel\MainLinkPanel;
use BlueSpice\Bookshelf\Panel\SidebarBookPanel;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Context\RequestContext;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\CommonUserInterface\Hook\MWStakeCommonUIRegisterSkinSlotComponents;
use MWStake\MediaWiki\Component\CommonUserInterface\TreeDataGenerator;

class CommonUserInterface implements MWStakeCommonUIRegisterSkinSlotComponents {

	/** @var ConfigFactory */
	private $configFactory = null;

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
	 * @param ConfigFactory $configFactory
	 * @param TitleFactory $titleFactory
	 * @param BookContextProviderFactory $bookContextProviderFactory
	 * @param BookLookup $bookLookup
	 * @param ChapterLookup $chapterLookup
	 * @param TreeDataGenerator $treeDataGenerator
	 */
	public function __construct(
		ConfigFactory $configFactory, TitleFactory $titleFactory,
		BookContextProviderFactory $bookContextProviderFactory,	BookLookup $bookLookup,
		ChapterLookup $chapterLookup, TreeDataGenerator $treeDataGenerator
	) {
		$this->configFactory = $configFactory;
		$this->titleFactory = $titleFactory;
		$this->bookContextProviderFactory = $bookContextProviderFactory;
		$this->bookLookup = $bookLookup;
		$this->chapterLookup = $chapterLookup;
		$this->treeDataGenerator = $treeDataGenerator;
	}

	/**
	 * @inheritDoc
	 */
	public function onMWStakeCommonUIRegisterSkinSlotComponents( $registry ): void {
		$config = $this->configFactory->makeConfig( 'bsg' );
		if ( $config->get( 'BookshelfMainLinksBookshelf' ) ) {
			$registry->register(
				'MainLinksPanel',
				[
					'bs-special-bookshelf' => [
						'factory' => static function () {
							return new MainLinkPanel();
						},
						'position' => 30
					]
				]
			);
		}

		$context = RequestContext::getMain();
		$title = $context->getTitle();
		if ( $title === null ) {
			return;
		}
		$titleFactory = $this->titleFactory;
		$bookContextProviderFactory = $this->bookContextProviderFactory;
		$bookLookup = $this->bookLookup;
		$chapterLookup = $this->chapterLookup;
		$treeDataGenerator = $this->treeDataGenerator;

		$registry->register(
			"SidebarPrimaryTabPanels",
			[
				'book' => [
					'factory' => static function () use (
						$title, $titleFactory, $bookContextProviderFactory,
						$bookLookup, $chapterLookup, $treeDataGenerator
					) {
						return new SidebarBookPanel(
							$title, $titleFactory, $bookContextProviderFactory, $bookLookup,
							$chapterLookup, $treeDataGenerator
						);
					}
				]
			]
		);

		if ( $config->get( 'BookShelfShowChapterNavigationPagerBeforeContent' ) ) {
			$registry->register(
				'DataBeforeContent', [
					'chapter-pager' => [
						'factory' => static function () use (
							$title, $titleFactory, $bookContextProviderFactory, $bookLookup, $chapterLookup
						) {
							return new ChapterPagerPanel(
							$title, $titleFactory, $bookContextProviderFactory, $bookLookup, $chapterLookup,
							'bs-bookshelfui-chapter-pager-cnt-top'
							);
						}
					]
				]
			);
		}
		if ( $config->get( 'BookShelfShowChapterNavigationPagerAfterContent' ) ) {
			$registry->register(
				'DataAfterContent', [
					'chapter-pager' => [
						'factory' => static function () use (
								$title, $titleFactory, $bookContextProviderFactory, $bookLookup, $chapterLookup
							) {
							return new ChapterPagerPanel(
								$title, $titleFactory, $bookContextProviderFactory, $bookLookup, $chapterLookup,
								'bs-bookshelfui-chapter-pager-bottom-top'
							);
						},
						'position' => 1
					]
				]
			);
		}
	}
}
