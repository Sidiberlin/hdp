<?php

namespace BlueSpice\Bookshelf\Panel;

use BlueSpice\Bookshelf\BookContextProviderFactory;
use BlueSpice\Bookshelf\BookLookup;
use BlueSpice\Bookshelf\TreeDataProvider;
use IContextSource;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleTreeContainer;
use MWStake\MediaWiki\Component\CommonUserInterface\TreeDataGenerator;
use Title;
use TitleFactory;

class BookNavigationTreeContainer extends SimpleTreeContainer {

	/** @var Title */
	private $title = null;

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var array */
	private $treeData = [];

	/** @var BookContextProviderFactory */
	private $bookContextProviderFactory = null;

	/** @var BookLookup */
	private $bookLookup = null;

	/** @var TreeDataGenerator */
	private $treeDataGenerator = null;

	/** @var Title|null */
	private $forceActiveBook = null;

	/** @var string */
	private $idPrefix = null;

	/**
	 * @param Title $title
	 * @param TitleFactory $titleFactory
	 * @param BookContextProviderFactory $bookContextProviderFactory
	 * @param BookLookup $bookLookup
	 * @param TreeDataGenerator $treeDataGenerator
	 * @param Title|null $forceActiveBook
	 * @param string $idPrefix
	 */
	public function __construct(
		Title $title, TitleFactory $titleFactory, BookContextProviderFactory $bookContextProviderFactory,
		BookLookup $bookLookup, TreeDataGenerator $treeDataGenerator, $forceActiveBook = null, string $idPrefix = ''
	) {
		parent::__construct( [] );

		$this->title = $title;
		$this->titleFactory = $titleFactory;
		$this->bookContextProviderFactory = $bookContextProviderFactory;
		$this->bookLookup = $bookLookup;
		$this->treeDataGenerator = $treeDataGenerator;
		$this->forceActiveBook = $forceActiveBook;
		$this->idPrefix = $idPrefix;
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return 'bs-book-tree-nav';
	}

	/**
	 * @inheritDoc
	 */
	public function getSubComponents(): array {
		$nodes = $this->treeDataGenerator->generate(
			$this->getTreeData(),
			$this->getTreeExpandPaths()
		);

		return $nodes;
	}

	/**
	 * @return array
	 */
	private function getTreeData(): array {
		if ( !empty( $this->treeData ) ) {
			return $this->treeData;
		}

		$treeDataProvider = new TreeDataProvider(
			$this->bookLookup, $this->bookContextProviderFactory, $this->titleFactory, $this->idPrefix
		);

		$this->treeData = $treeDataProvider->get( $this->title, $this->forceActiveBook );

		return $this->treeData;
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function shouldRender( IContextSource $context ): bool {
		return true;
	}

	/**
	 * @return array
	 */
	private function getTreeExpandPaths(): array {
		$treeData = $this->getTreeData();

		$activeBook = $this->forceActiveBook;
		$book = '';
		if ( $activeBook === null ) {

			$books = $this->bookLookup->getBooksForPage( $this->title );
			if ( empty( $books ) ) {
				return [];
			}
			$bookContextProvider = $this->bookContextProviderFactory->getProvider( $this->title );
			$activeBook = $bookContextProvider->getActiveBook();
			if ( $activeBook ) {
				$book = $activeBook->getPrefixedDBkey();
			}
		}

		$name = md5( $book . $this->title->getPrefixedDBkey() );

		$paths = $this->makeTreePathsList( $treeData, $activeBook->getPrefixedDBkey() );
		if ( isset( $paths[$name] ) ) {
			return [
				$paths[$name]
			];
		}

		return [];
	}

	/**
	 * @param array $items
	 * @param string $book
	 * @return array
	 */
	private function makeTreePathsList( array $items, string $book ): array {
		$paths = [];
		foreach ( $items as $item ) {
			$name = md5( $book . $item['name'] );
			if ( $item['type'] === 'wikilink-with-alias' ) {
				$title = $this->titleFactory->makeTitle( $item['namespace'], $item['title'] );
				$name = md5( $book . $title->getPrefixedDBkey() );
			}

			$path = $item['path'];

			$paths[$name] = trim( $path, '/' );

			if ( isset( $item['items'] ) && !empty( $item['items'] ) ) {
				$paths = $paths + $this->makeTreePathsList( $item['items'], $book );
			}
		}

		return $paths;
	}

	/**
	 * @return string[]
	 */
	public function getRequiredRLStyles(): array {
		return [];
	}

	/**
	 * @return array
	 */
	public function getRequiredRLModules(): array {
		return [ 'mwstake.component.commonui.tree-component' ];
	}
}
