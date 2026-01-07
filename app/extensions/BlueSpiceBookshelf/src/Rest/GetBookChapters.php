<?php

namespace BlueSpice\Bookshelf\Rest;

use BlueSpice\Bookshelf\BookLookup;
use BlueSpice\Bookshelf\ChapterDataModel;
use BlueSpice\Bookshelf\ChapterInfo;
use BlueSpice\Bookshelf\ChapterLookup;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class GetBookChapters extends SimpleHandler {

	/** @var BookLookup */
	private $bookLookup;

	/** @var ChapterLookup */
	private $chapterLookup;

	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param BookLookup $bookLookup
	 * @param ChapterLookup $chapterLookup
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( BookLookup $bookLookup,
	ChapterLookup $chapterLookup, TitleFactory $titleFactory ) {
		$this->bookLookup = $bookLookup;
		$this->chapterLookup = $chapterLookup;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$params = $this->getValidatedParams();
		$bookID = (int)$params['bookID'] ?? -1;
		$chapterNumber = $params['chapterNumber'] ?? '';
		$node = $params['node'] ?? null;
		$expandPaths = isset( $params['expand-paths'] ) ? json_decode( $params['expand-paths'] ) : [];

		$title = $this->titleFactory->newFromText( $node );

		if ( !$title ) {
			return $this->getResponseFactory()->createHttpError( 404, [ 'error' => 'No valid title' ] );
		}

		$activeBook = $this->bookLookup->getBookTitleFromID( $bookID );

		if ( !$activeBook ) {
			return $this->getResponseFactory()->createHttpError( 404, [ 'error' => 'No valid book selected' ] );
		}

		$bookID = $this->bookLookup->getBookId( $activeBook );

		if ( empty( $expandPaths ) ) {
			$chapterInfo = $this->chapterLookup->getChapterInfoForNumber( $bookID, $chapterNumber );
			$chapters = $this->chapterLookup->getChapterChildrenForBookId( $bookID, $chapterInfo );
		} else {
			$expandTitle = $this->titleFactory->newFromText( $expandPaths[0] );
			$chapterInfo = $this->chapterLookup->getChapterInfoFor( $activeBook, $expandTitle );
			$chapters = $this->chapterLookup->getFirstChapterForBookId( $bookID );
		}

		$result = $this->prepareResults( $chapters, $bookID, $chapterInfo );

		return $this->getResponseFactory()->createJson( [ 'results' => $result ] );
	}

	/**
	 * @param ChapterDataModel[] $chapters
	 * @param int $bookID
	 * @param ChapterInfo $expandChapter
	 * @return void
	 */
	private function prepareResults( $chapters, $bookID, $expandChapter ) {
		$nodes = [];
		$currentNumber = $expandChapter->getNumber();
		$parts = explode( '.', $currentNumber );

		$paths = [];
		for ( $i = 1; $i <= count( $parts ); $i++ ) {
			$paths[] = implode( '.', array_slice( $parts, 0, $i ) );
		}
		foreach ( $chapters as $chapter ) {
			$children = $this->chapterLookup->getChapterChildrenForBookId( $bookID, $chapter );

			if ( $chapter->getType() === 'plain-text' ) {
				$node = [
					'id' => $chapter->getName(),
					'number' => $chapter->getNumber(),
					'label' => $chapter->getName(),
					'title' => $chapter->getName(),
					'url' => '',
					'exists' => true,
				];
			} else {
				$chapterTitle = $this->titleFactory->newFromText( $chapter->getTitle(), $chapter->getNamespace() );
				$node = [
					'id' => Sanitizer::escapeIdForAttribute(
						"{$chapter->getNumber()}_{$chapterTitle->getPrefixedDBkey()}"
					),
					'number' => $chapter->getNumber(),
					'title' => $chapterTitle->getPrefixedDBkey(),
					'prefixed' => $chapterTitle->getPrefixedText(),
					'label' => $chapter->getName(),
					'url' => $chapterTitle->getLocalURL(),
					'exists' => $chapterTitle->exists(),
				];
			}
			$node['leaf'] = !empty( $children ) ? false : true;
			if ( !$this->setChildren( $chapter->getNumber(), $paths ) ||
				$chapter->getName() === $expandChapter->getName() ) {
				$node['children'] = [];
				$nodes[] = $node;
				continue;
			}
			$node['children'] = $this->prepareResults( $children, $bookID, $expandChapter );
			$nodes[] = $node;
		}
		return $nodes;
	}

	/**
	 * @param int $number
	 * @param int[] $paths
	 * @return void
	 */
	private function setChildren( $number, $paths ) {
		return in_array( $number, $paths );
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		return [
			'bookID' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'chapterNumber' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'node' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'expand-paths' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			]
		];
	}

}
