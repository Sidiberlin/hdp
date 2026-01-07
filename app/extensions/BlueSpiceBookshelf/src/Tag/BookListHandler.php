<?php

namespace BlueSpice\Bookshelf\Tag;

use BlueSpice\Bookshelf\BookLookup;
use BlueSpice\Bookshelf\BookMetaLookup;
use BlueSpice\Tag\Handler;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Title\TitleFactory;
use Wikimedia\Rdbms\LoadBalancer;

class BookListHandler extends Handler {
	/**
	 * @var TitleFactory
	 */
	protected $titleFactory = null;

	/**
	 * @var LoadBalancer
	 */
	protected $lb = null;

	/**
	 * @var LinkRenderer
	 */
	protected $linkRenderer = null;

	/** @var BookLookup */
	private $bookLookup = null;

	/** @var BookMetaLookup */
	private $bookMetaLookup = null;

	/**
	 * @param string $processedInput
	 * @param array $processedArgs
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param TitleFactory $titleFactory
	 * @param LoadBalancer $lb
	 * @param LinkRenderer $linkRenderer
	 * @param BookLookup $bookLookup
	 * @param BookMetaLookup $bookMetaLookup
	 */
	public function __construct(
		$processedInput, array $processedArgs, Parser $parser,
		PPFrame $frame, TitleFactory $titleFactory, LoadBalancer $lb,
		LinkRenderer $linkRenderer, BookLookup $bookLookup, BookMetaLookup $bookMetaLookup
	) {
		parent::__construct( $processedInput, $processedArgs, $parser, $frame );
		$this->titleFactory = $titleFactory;
		$this->lb = $lb;
		$this->linkRenderer = $linkRenderer;
		$this->bookLookup = $bookLookup;
		$this->bookMetaLookup = $bookMetaLookup;
	}

	/**
	 *
	 * @return string
	 */
	public function handle() {
		$filters = explode( '|', trim( $this->processedArgs[BookList::PARAM_FILTER] ) );
		$parsedFilters = [];
		foreach ( $filters as $keyValuePair ) {
			$parts = explode( ':', trim( $keyValuePair ), 2 );
			if ( count( $parts ) < 2 ) {
				continue;
			}
			$parsedFilters[trim( $parts[0] )] = trim( $parts[1] );
		}
		// TODO RBV (19.12.11 16:32): error message if invalid filter

		$books = $this->bookLookup->getBooks();

		$bookList = [];
		foreach ( $books as $book ) {
			$title = $this->titleFactory->makeTitle( $book->getNamespace(), $book->getTitle() );
			if ( !$title || !$title->exists() ) {
				continue;
			}

			$meta = $this->bookMetaLookup->getMetaForBook( $title );
			if ( !isset( $meta['title'] ) ) {
				$meta['title'] = $book->getName();
			}
			if ( !isset( $meta['source'] ) ) {
				$meta['source'] = $title->getPrefixedText();
			}

			if ( !empty( $parsedFilters ) ) {
				$match = false;
				foreach ( $parsedFilters as $key => $value ) {
					if ( empty( $meta[$key] ) ) {
						continue;
					}
					if ( strpos( $meta[$key], $value ) === false ) {
						continue;
					}
					$match = true;
					break;
				}
				if ( !$match ) {
					// Not what we are looking for
					continue;
				}
			}

			$link = $this->linkRenderer->makeLink( $title, $book->getName() );

			$bookList[] = [
				'meta' => $meta,
				'link' => $link
			];
		}

		// TODO RBV (20.12.11 10:30): Display meta in tooltip...
		// TODO: allow PDF links to be injected
		$out = Html::openElement( 'ul' );
		foreach ( $bookList as $bookLIstItem ) {
			$out .= Html::openElement( 'li' );
			$out .= $bookLIstItem['link'];
			$out .= Html::closeElement( 'li' );
		}
		$out .= Html::closeElement( 'ul' );

		return $out;
	}
}
