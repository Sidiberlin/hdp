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
use MWStake\MediaWiki\Lib\Nodes\INode;

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
	 * @inheritDoc
	 */
	public function addNodeAfter( INode $node, mixed $afterNode, bool $newline = true ): void {
		$nodes = $this->parse();
		if ( !$afterNode || empty( $nodes ) ) {
			$this->addNode( $node, 'prepend', $newline );
			return;
		}
		$nodes = $this->getChapterDataModels();

		foreach ( $nodes as $nodeData ) {
			$existingNode = $nodeData[0];
			/** @var ChapterDataModel $existingNodeModel */
			$existingNodeModel = $nodeData[1];
			if ( $existingNodeModel->getNumber() === $afterNode ) {
				// Found "after node", insert after
				// First match, in case there are multiple
				parent::addNodeAfter( $node, $existingNode, $newline );
				return;
			}
		}
	}

	/**
	 * @param PageUpdater $updater
	 * @return void
	 */
	protected function setUpdaterSlotsOnSave( PageUpdater $updater ) {
		parent::setUpdaterSlotsOnSave( $updater );
		$metaSlot = $this->revision->hasSlot( 'book_meta' ) ?
			$this->revision->getContent( 'book_meta' ) : new JsonContent( FormatJson::encode( [] ) );
		$updater->setContent( 'book_meta', $metaSlot );
	}

	/**
	 * @return ChapterDataModel[]
	 */
	public function getChapterDataModelArray(): array {
		$chapters = $this->getChapterDataModels();
		return array_map( static function ( $chapter ) {
			return $chapter[1];
		}, $chapters );
	}

	/**
	 * @return array
	 */
	private function getChapterDataModels(): array {
		$nodes = $this->parse();

		$lastLevel = 1;
		$number = [];
		$chapters = [];
		foreach ( $nodes as $node ) {
			if ( $node instanceof MenuNode === false ) {
				continue;
			}
			if ( $node instanceof ChapterPlainText ) {
				$chapters[] = [ $node, new ChapterDataModel(
					null,
					null,
					$node->getNodeText(),
					$this->buildChapterNumber( $node->getLevel(), $lastLevel, $number ),
					ChapterDataModel::PLAIN_TEXT
				) ];
			} elseif ( $node instanceof ChapterWikiLinkWithAlias ) {
				$target = $this->titleFactory->newFromText( $node->getTarget() );

				$name = $node->getLabel();
				if ( $name === '' ) {
					$name = $target->getText();
				}

				$chapters[] = [ $node, new ChapterDataModel(
					$target->getNamespace(),
					$target->getDBkey(),
					$name,
					$this->buildChapterNumber( $node->getLevel(), $lastLevel, $number ),
					ChapterDataModel::WIKILINK_WITH_ALIAS
				) ];
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
