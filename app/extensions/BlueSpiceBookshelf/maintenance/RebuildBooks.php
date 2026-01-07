<?php

use BlueSpice\Bookshelf\BookSourceParser;
use BlueSpice\Bookshelf\ChapterDataModel;
use MediaWiki\Content\TextContent;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\Wikitext\ParserFactory;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LoadBalancer;

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

class RebuildBooks extends LoggedUpdateMaintenance {

	/** @var IDatabase */
	private $db = null;

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var WikiPageFactory */
	private $wikiPageFactory = null;

	/** @var RevisionLookup */
	private $revisionLookup = null;

	/** @var ParserFactory */
	private $parserFactory = null;

	/** @var Title[] */
	private $books = [];

	/** @var array */
	private $invalidBookIDs = [];

	/** @var array */
	private $existingBooks = [];

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'bookshelf-rebuild-books';
	}

	/**
	 *
	 */
	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'BlueSpiceBookshelf' );
	}

	/**
	 * @return bool
	 */
	protected function doDBUpdates() {
		$this->output( "BlueSpiceBookshelf - update tables bs_books and bs_book_chapters\n" );

		$services = MediaWikiServices::getInstance();

		/** @var LoadBalancer */
		$loadBalancer = $services->getDBLoadBalancer();

		$this->db = $loadBalancer->getConnection( DB_PRIMARY );
		$this->titleFactory = $services->getTitleFactory();
		$this->wikiPageFactory = $services->get( 'WikiPageFactory' );
		$this->revisionLookup = $services->getRevisionLookup();
		$this->parserFactory = $services->get( 'MWStakeWikitextParserFactory' );

		$this->fetchBooks();
		$this->updateBooksTable();
		$this->updateBookMetaTable();
		$this->updateBookChaptersTable();

		return true;
	}

	private function fetchBooks() {
		$res = $this->db->select(
			'page',
			'*',
			[
				'page_content_model' => 'book'
			],
			__METHOD__
		);

		if ( $res->numRows() < 1 ) {
			return;
		}

		foreach ( $res as $row ) {
			if ( (int)$row->page_namespace === NS_USER ) {
				continue;
			}
			$title = $this->titleFactory->makeTitle( $row->page_namespace, $row->page_title );
			$key = $title->getPrefixedDBkey();
			$this->books[$key] = $title;
		}
	}

	private function updateBooksTable() {
		$res = $this->db->select(
			'bs_books',
			[ 'book_id', 'book_namespace', 'book_title' ],
			'',
			__METHOD__
		);

		foreach ( $res as $row ) {
			$title = $this->titleFactory->makeTitle( $row->book_namespace, $row->book_title );
			$key = $title->getPrefixedDBkey();
			if ( !isset( $this->books[$key] ) ) {
				$this->invalidBookIDs[] = $row->book_id;
			} else {
				$this->existingBooks[] = $key;
			}
		}

		// Delete not existing books
		if ( !empty( $this->invalidBookIDs ) ) {
			$this->output( "Cleaning table 'bs_books' ..." );
			$this->db->delete(
				'bs_books',
				[ 'book_id' => $this->invalidBookIDs ],
				__METHOD__
			);
			$this->output( "\033[32m  done\n\033[39m" );
		}

		// Add new books
		foreach ( $this->books as $key => $book ) {
			$this->output( "Insert into 'bs_books' " . $book->getPrefixedDBKey() );
			if ( in_array( $key, $this->existingBooks ) ) {
				$this->output( "\033[32m  skipping\n\033[39m" );
				continue;
			}

			$this->db->insert(
				'bs_books',
				[
					'book_namespace' => $book->getNamespace(),
					'book_title' => $book->getDBkey(),
					'book_name' => $book->getText(),
					'book_type' => 'public'
				],
				__METHOD__
			);
			$this->output( "\033[32m  done\n\033[39m" );
		}
	}

	private function updateBookMetaTable() {
		// Delete unused book meta
		if ( !empty( $this->invalidBookIDs ) ) {
			$this->output( "Cleaning table 'bs_book_meta' ..." );
			$this->db->delete(
				'bs_book_meta',
				[ 'm_book_id' => $this->invalidBookIDs ],
				__METHOD__
			);
			$this->output( "\033[32m  done\n\033[39m" );
		}

		// Add new book meta
		foreach ( $this->books as $key => $book ) {
			if ( in_array( $key, $this->existingBooks ) ) {
				continue;
			}

			$meta = [];

			$res = $this->db->select(
				'bs_books',
				'book_id',
				[
					'book_namespace' => $book->getNamespace(),
					'book_title' => $book->getDBkey()
				],
				__METHOD__
			);

			$bookId = null;
			foreach ( $res as $row ) {
				$bookId = $row->book_id;
			}

			if ( $bookId === null ) {
				continue;
			}

			$wikiPage = $this->wikiPageFactory->newFromLinkTarget( $book );
			$content = $wikiPage->getContent();

			if ( $content instanceof TextContent ) {
				$text = $content->getText();

				$tagMatches = [];
				preg_match( '#<bookmeta (.*?)/>#', $text, $tagMatches );

				if ( count( $tagMatches ) > 0 ) {
					$matches = [];
					preg_match_all( '#(.*?)=\"(.*?)\"#', $tagMatches[1], $matches );

					if ( count( $matches ) > 0 ) {
						for ( $index = 0; $index < count( $matches[0] ); $index++ ) {
							$key = $matches[1][$index];
							$value = $matches[2][$index];

							$meta[$key] = $value;
						}
					}
				}
			}

			foreach ( $meta as $key => $value ) {
				$this->output( "Insert book meta of " . $book->getPrefixedDBKey() . " in 'bs_book_meta' ..." );
				$this->db->insert(
					'bs_book_meta',
					[
						'm_book_id' => $bookId,
						'm_key' => trim( $key ),
						'm_value' => trim( $value )
					],
					__METHOD__
				);
				if ( $key === 'title' ) {
					// Update book name
					$this->db->update(
						'bs_books',
						[ 'book_name' => $value ],
						[ 'book_id' => $bookId ],
						__METHOD__
					);
				}
				$this->output( "\033[32m  done\n\033[39m" );
			}
		}
	}

	private function updateBookChaptersTable() {
		// Delete unused book chapters
		if ( !empty( $this->invalidBookIDs ) ) {
			$this->output( "Cleaning table 'bs_book_chapters' ..." );
			$this->db->delete(
				'bs_book_chapters',
				[ 'chapter_book_id' => $this->invalidBookIDs ],
				__METHOD__
			);
			$this->output( "\033[32m  done\n\033[39m" );
		}

		// Add new book chapters
		foreach ( $this->books as $book ) {
			$revisionRecord = $this->revisionLookup->getRevisionByTitle( $book );
			if ( !$revisionRecord ) {
				$this->output( "\033[31mNo valid revision for " . $book->getPrefixedDBKey() . "\n\033[39m" );
				continue;
			}
			$bookSourceParser = new BookSourceParser(
				$revisionRecord,
				$this->parserFactory->getNodeProcessors(),
				$this->titleFactory
			);

			/** @var ChapterDataModel */
			$chapters = $bookSourceParser->getChapterDataModelArray();

			$res = $this->db->select(
				'bs_books',
				'book_id',
				[
					'book_namespace' => $book->getNamespace(),
					'book_title' => $book->getDBkey()
				],
				__METHOD__
			);

			$bookId = null;
			foreach ( $res as $row ) {
				$bookId = $row->book_id;
			}

			if ( $bookId !== null ) {
				foreach ( $chapters as $chapter ) {
					$this->output( "Insert " . $chapter->getName() . " into 'bs_book_chapters' ..." );
					$this->db->insert(
						'bs_book_chapters',
						[
							'chapter_book_id' => $bookId,
							'chapter_namespace' => $chapter->getNamespace(),
							'chapter_title' => $chapter->getTitle(),
							'chapter_name' => $chapter->getName(),
							'chapter_number' => $chapter->getNumber(),
							'chapter_type' => $chapter->getType()
						],
						__METHOD__
					);
					$this->output( "\033[32m  done\n\033[39m" );
				}
			} else {
				$this->output( "\033[31mNo valid book_id for " . $book->getPrefixedDBKey() . "\n\033[39m" );
			}
		}
	}
}

$maintClass = RebuildBooks::class;
require_once RUN_MAINTENANCE_IF_MAIN;
