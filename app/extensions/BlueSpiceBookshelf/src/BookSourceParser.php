<?php

namespace BlueSpice\Bookshelf;

use BlueSpice\Bookshelf\Content\BookContent;
use BlueSpice\Bookshelf\MenuEditor\Node\ChapterPlainText;
use BlueSpice\Bookshelf\MenuEditor\Node\ChapterWikiLinkWithAlias;
use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContent;
use MediaWiki\Extension\MenuEditor\Node\MenuNode;
use MediaWiki\Extension\MenuEditor\Parser\WikitextMenuParser;
use MediaWiki\Json\FormatJson;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Title\TitleFactory;

class BookSourceParser extends WikitextMenuParser {

	/** @var TitleFactory */
	private $titleFactory = null;

	/**
	 * @param RevisionRecord $revision
	 * @param array $nodeProcessors
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( RevisionRecord $revision, array $nodeProcessors, TitleFactory $titleFactory ) {
		$this->titleFactory = $titleFactory;
		parent::__construct( $revision, $nodeProcessors );
	}

	/**
	 * @param array $nodes
	 * @param bool $replace
	 * @return void
	 */
	public function addNodesFromData( array $nodes, bool $replace = false ) {
		parent::addNodesFromData( $nodes['nodes'], $replace );
		$meta = $nodes['metadata'] ?? [];
		$content = new JsonContent( FormatJson::encode( $meta ) );
		$this->revision->setContent( 'book_meta', $content );
	}

	/**
	 * @param PageUpdater $updater
	 * @return void
	 */
	protected function setUpdaterSlotsOnSave( PageUpdater $updater ) {
		parent::setUpdaterSlotsOnSave( $updater );
		$updater->setContent( 'book_meta', $this->revision->getContent( 'book_meta' ) );
	}

	/**
	 * @return DataChapterModel[]
	 */
	public function getChapterDataModelArray(): array {
		$nodes = $this->parse();

		$lastLevel = 1;
		$number = [];
		$chapters = [];
		foreach ( $nodes as $node ) {
			if ( $node instanceof MenuNode === false ) {
				continue;
			}
			if ( $node instanceof ChapterPlainText ) {
				$chapters[] = new ChapterDataModel(
					null,
					null,
					$node->getNodeText(),
					$this->buildChapterNumber( $node->getLevel(), $lastLevel, $number ),
					ChapterDataModel::PLAIN_TEXT
				);
			} elseif ( $node instanceof ChapterWikiLinkWithAlias ) {
				$target = $this->titleFactory->newFromText( $node->getTarget() );

				$name = $node->getLabel();
				if ( $name === '' ) {
					$name = $target->getText();
				}

				$chapters[] = new ChapterDataModel(
					$target->getNamespace(),
					$target->getDBkey(),
					$name,
					$this->buildChapterNumber( $node->getLevel(), $lastLevel, $number ),
					ChapterDataModel::WIKILINK_WITH_ALIAS
				);
			} else {
				continue;
			}

			$lastLevel = $node->getLevel();
		}

		return $chapters;
	}

	/**
	 * @param int $level
	 * @param int $lastLevel
	 * @param array &$number
	 * @return string
	 */
	private function buildChapterNumber( int $level, int $lastLevel, array &$number ): string {
		$index = $level - 1;
		$lastIndex = $lastLevel - 1;

		if ( $level < $lastLevel ) {
			for ( $idx = $lastIndex; $idx >= $level; $idx-- ) {
				unset( $number[$idx] );
			}
		}

		if ( !isset( $number[$index] ) ) {
			$number[$index] = 0;
		}

		$number[$index]++;

		return implode( '.', $number );
	}

	/**
	 * @inheritDoc
	 */
	protected function getContentObject(): Content {
		return new BookContent( $this->rawData );
	}
}
