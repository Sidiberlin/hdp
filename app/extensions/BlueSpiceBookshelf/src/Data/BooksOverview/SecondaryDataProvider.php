<?php

namespace BlueSpice\Bookshelf\Data\BooksOverview;

use BlueSpice\Bookshelf\BookMetaLookup;
use BlueSpice\Bookshelf\BooksOverviewActions\BookMetaData;
use BlueSpice\Bookshelf\BooksOverviewActions\Delete;
use BlueSpice\Bookshelf\BooksOverviewActions\Edit;
use BlueSpice\Bookshelf\BooksOverviewActions\View;
use BlueSpice\Bookshelf\ChapterDataModel;
use BlueSpice\Bookshelf\ChapterLookup;
use BlueSpice\Bookshelf\IBooksOverviewAction;
use MediaWiki\Config\Config;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use RepoGroup;

class SecondaryDataProvider extends \MWStake\MediaWiki\Component\DataStore\SecondaryDataProvider {

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var PermissionManager */
	private $permissionManager = null;

	/** @var HookContainer */
	private $hookRunner = null;

	/** @var User */
	private $user = null;

	/** @var RepoGroup */
	private $repoGroup = null;

	/** @var Config */
	private $config = null;

	/** @var ChapterLookup */
	private $bookChapterLookup = null;

	/** @var BookMetaLookup */
	private $bookMetaLookup = null;

	/**
	 * Array with stock images for bookshelf image if not set in book meta data
	 * @var array
	 */
	private $stockImages = [
		"/extensions/BlueSpiceBookshelf/resources/images/BookshelfImages/bookshelfImage001.jpg",
		"/extensions/BlueSpiceBookshelf/resources/images/BookshelfImages/bookshelfImage002.jpg",
		"/extensions/BlueSpiceBookshelf/resources/images/BookshelfImages/bookshelfImage003.jpg",
		"/extensions/BlueSpiceBookshelf/resources/images/BookshelfImages/bookshelfImage004.jpg",
		"/extensions/BlueSpiceBookshelf/resources/images/BookshelfImages/bookshelfImage005.jpg",
		"/extensions/BlueSpiceBookshelf/resources/images/BookshelfImages/bookshelfImage006.jpg",
		"/extensions/BlueSpiceBookshelf/resources/images/BookshelfImages/bookshelfImage007.jpg",
		"/extensions/BlueSpiceBookshelf/resources/images/BookshelfImages/bookshelfImage008.jpg",
		"/extensions/BlueSpiceBookshelf/resources/images/BookshelfImages/bookshelfImage009.jpg"
	];

	/**
	 *
	 * @param TitleFactory $titleFactory
	 * @param PermissionManager $permissionManager
	 * @param HookContainer $hookRunner
	 * @param User $user
	 * @param RepoGroup $repoGroup
	 * @param Config $config
	 * @param ChapterLookup $bookChapterLookup
	 * @param BookMetaLookup $bookMetaLookup
	 */
	public function __construct(
		TitleFactory $titleFactory, PermissionManager $permissionManager,
		HookContainer $hookRunner, User $user, RepoGroup $repoGroup, Config $config, ChapterLookup $bookChapterLookup,
		BookMetaLookup $bookMetaLookup
	) {
		$this->titleFactory = $titleFactory;
		$this->permissionManager = $permissionManager;
		$this->user = $user;
		$this->hookRunner = $hookRunner;
		$this->repoGroup = $repoGroup;
		$this->config = $config;
		$this->bookChapterLookup = $bookChapterLookup;
		$this->bookMetaLookup = $bookMetaLookup;
	}

	/**
	 *
	 * @param Record &$dataSet
	 */
	protected function doExtend( &$dataSet ) {
		$book = $this->getBook(
			$dataSet->get( Record::BOOK_NAMESPACE ),
			$dataSet->get( Record::BOOK_TITLE )
		);

		$meta = $this->makeBookMeta( $book );

		$displayTitle = $this->makeDisplayTitle( $dataSet, $book, $meta );
		$dataSet->set( Record::DISPLAYTITLE, $displayTitle );

		$subtitle = $this->makeSubtitle( $meta );
		$dataSet->set( Record::SUBTITLE, $subtitle );

		$bookshelf = $this->makeBookshelf( $meta );
		$dataSet->set( Record::BOOKSHELF, $bookshelf );

		$localUrl = $this->makeFirstChapterURL( $book );
		$dataSet->set( Record::FIRST_CHAPTER_URL, $localUrl );

		$editUrl = $this->makeBookEditURL( $book );
		$dataSet->set( Record::BOOK_EDIT_URL, $editUrl );

		$imageURL = $this->makeImageURL( $meta );
		$dataSet->set( Record::IMAGE_URL, $imageURL );

		$this->setActions( $dataSet, $displayTitle );
	}

	/**
	 * @param Title $book
	 * @return string
	 */
	private function makeBookEditURL( Title $book ): string {
		if ( !$book->isKnown() ) {
			return '';
		}
		$chapters = $this->bookChapterLookup->getChaptersOfBook( $book );
		if ( !empty( $chapters ) ) {
			return '';
		}
		return $book->getEditURL();
	}

	/**
	 * @param Title $book
	 * @return string
	 */
	private function makeFirstChapterURL( Title $book ): string {
		$localUrl = '';

		if ( $book->isKnown() ) {
			$chapters = $this->bookChapterLookup->getChaptersOfBook( $book );
			if ( empty( $chapters ) ) {
				return $localUrl;
			}

			$chapterDataModel = null;
			foreach ( $chapters as $chapter ) {
				if ( $chapter instanceof ChapterDataModel === false ) {
					continue;
				}

				if ( $chapter->getType() === 'plain-text' ) {
					continue;
				}

				$chapterDataModel = $chapter;
				break;
			}

			if ( $chapterDataModel === null ) {
				return $localUrl;
			}

			$chapterPage = $this->titleFactory->makeTitle(
				$chapterDataModel->getNamespace(),
				$chapterDataModel->getTitle()
			);

			if ( !$chapterPage ) {
				return $localUrl;
			}

			$text = $book->getFullText();
			$localUrl = $chapterPage->getLocalURL( [ 'book' => $text ] );
		}

		return $localUrl;
	}

	/**
	 * @param Title $book
	 * @return array
	 */
	private function makeBookMeta( Title $book ): array {
		$requiredMeta = [
			'title' => $book->getText(),
			'subtitle' => '',
			'bookshelfimage' => '',
			'bookshelf' => ''
		];

		$meta = $this->bookMetaLookup->getMetaForBook( $book );

		return array_merge( $requiredMeta, $meta );
	}

	/**
	 * @param Record &$dataSet
	 * @param Title $book
	 * @param array $meta
	 * @return string
	 */
	private function makeDisplayTitle( Record &$dataSet, Title $book, array $meta ): string {
		$displayTitle = $dataSet->get( Record::DISPLAYTITLE );
		if ( $displayTitle === $dataSet->get( Record::BOOK_TITLE ) ) {
			$displayTitle = $book->getText();
		}

		if ( isset( $meta['title'] ) && $meta['title'] !== '' ) {
			$displayTitle = $meta['title'];
		}
		return $displayTitle;
	}

	/**
	 * @param array $meta
	 * @return string
	 */
	private function makeSubtitle( array $meta ): string {
		$subtitle = '';

		if ( isset( $meta['subtitle'] ) && $meta['subtitle'] !== '' ) {
			$subtitle = $meta['subtitle'];
		}

		return $subtitle;
	}

	/**
	 * @param array $meta
	 * @return string
	 */
	private function makeBookshelf( array $meta ): string {
		$bookshelf = '';

		if ( isset( $meta['bookshelf'] ) && $meta['bookshelf'] !== '' ) {
			$bookshelf = $meta['bookshelf'];
		}

		return $bookshelf;
	}

	/**
	 * @param array $meta
	 * @return string
	 */
	private function makeImageURL( array $meta ): string {
		$path = $meta['bookshelfimage'];

		if ( isset( $meta['bookshelfimage'] ) && $meta['bookshelfimage'] !== '' ) {
			$basename = basename( $meta['bookshelfimage'] );

			$fileTitle = $this->titleFactory->newFromText( $basename );
			if ( !$fileTitle->exists() || $fileTitle->getNamespace() !== NS_FILE ) {
				$fileTitle = $this->titleFactory->makeTitle( NS_FILE, $basename );
			}

			$localFile = $this->repoGroup->findFile( $fileTitle );
			if ( $localFile !== false ) {
				$thumb = $localFile->transform( [
					'width' => 350
				] );
				$path = $thumb->getURL();
			}
		} else {
			$numOfCharInTitle = strlen( $meta['title'] );
			$numOfStockImages = count( $this->stockImages );
			$index = $numOfCharInTitle % $numOfStockImages;
			$server = $this->config->get( 'Server' );
			$scriptPath = $this->config->get( 'ScriptPath' );
			$path = $server . $scriptPath . $this->stockImages[$index];
		}

		return $path;
	}

	/**
	 * @param Record &$dataSet
	 * @param string $displayTitle
	 */
	private function setActions( Record &$dataSet, string $displayTitle ) {
		$actions = [];

		$book = $this->getBook(
			$dataSet->get( Record::BOOK_NAMESPACE ),
			$dataSet->get( Record::BOOK_TITLE )
		);

		if ( $this->user instanceof User ) {
			$actionsItems = [];

			$this->setDefaultActionItems( $actionsItems, $book, $displayTitle );
			$this->setExternalActionItems( $actionsItems, $book, $displayTitle );

			$this->sortActionItems( $actionsItems );
			$modules = [];
			foreach ( $actionsItems as $actionItem ) {
				if ( $actionItem instanceof IBooksOverviewAction === false ) {
					continue;
				}

				if ( $this->permissionManager->quickUserCan(
					$actionItem->getRequiredPermission(), $this->user, $book )
				) {
					$name = $actionItem->getName();

					$actionData = [
						'iconClass' => implode( ' ', $actionItem->getIconClasses() ),
						'class' => implode( ' ', $actionItem->getClasses() ),
						'text' => $actionItem->getText()->text(),
						'title' => $actionItem->getTitle()->text(),
						'href' => $actionItem->getHref(),
						'book' => $book->getPrefixedDBkey(),
					];
					$modules = array_merge( $actionItem->getRLModules(), $modules );

					$actions[$name] = $actionData;
				}
			}
		}

		$dataSet->set(
			Record::ACTIONS,
			$actions
		);
		array_unique( $modules );
		$dataSet->set(
			Record::ACTIONS_MODULES,
			$modules
		);
	}

	/**
	 * Allow other extensions to hook in and add their own actions
	 *
	 * @param array &$actions
	 * @param Title $book
	 * @param string $displayTitle
	 */
	private function setExternalActionItems( array &$actions, Title $book, string $displayTitle ) {
		$this->hookRunner->run( 'BSBookshelfBooksOverviewBeforeSetBookActions', [ &$actions, $book, $displayTitle ] );
	}

	/**
	 * Set the default actions
	 * edit, move and delete
	 *
	 * @param array &$actions
	 * @param Title $book
	 * @param string $displayTitle
	 */
	private function setDefaultActionItems( array &$actions, Title $book, string $displayTitle ) {
		$actions = [];

		// View action
		$actions['view'] = new View( $book, $displayTitle );

		// Delete action
		$actions['delete'] = new Delete( $book, $displayTitle );

		// Edit action
		$actions['edit'] = new Edit( $book, $displayTitle );

		$actions['metadata' ] = new BookMetaData( $book, $displayTitle );
	}

	/**
	 * @param array &$actions
	 */
	private function sortActionItems( array &$actions ) {
		usort( $actions, static function ( $actionA, $actionB ) {
			$positionA = $actionA->getPosition();
			$positionB = $actionB->getPosition();

			return $positionA > $positionB ? 1 : 0;
		} );
	}

	/**
	 * @param int $namespace
	 * @param string $title
	 * @return Title
	 */
	private function getBook( int $namespace, string $title ): Title {
		return $this->titleFactory->makeTitleSafe( $namespace, $title );
	}
}
