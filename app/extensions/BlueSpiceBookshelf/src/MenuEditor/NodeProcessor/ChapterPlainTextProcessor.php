<?php

namespace BlueSpice\Bookshelf\MenuEditor\NodeProcessor;

use BlueSpice\Bookshelf\MenuEditor\Node\ChapterPlainText;
use MediaWiki\Extension\MenuEditor\Node\RawText;
use MediaWiki\Extension\MenuEditor\NodeProcessor\MenuNodeProcessor;
use MWStake\MediaWiki\Component\Wikitext\NodeSource\WikitextSource;
use MWStake\MediaWiki\Lib\Nodes\INode;
use MWStake\MediaWiki\Lib\Nodes\INodeSource;

class ChapterPlainTextProcessor extends MenuNodeProcessor {
	/**
	 * @param string $wikitext
	 * @return bool
	 */
	public function matches( $wikitext ): bool {
		return (bool)preg_match( '/^(\*{1,})([^\{\[\]\}\|]*?)$/', $wikitext );
	}

	/**
	 * @param INodeSource|WikitextSource $source
	 * @return INode
	 */
	public function getNode( INodeSource $source ): INode {
		return new ChapterPlainText(
			$this->getLevel( $source->getWikitext() ),
			$this->getNodeValue( $source->getWikitext() ),
			$source->getWikitext()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function supportsNodeType( $type ): bool {
		return $type === 'bs-bookshelf-chapter-plain-text';
	}

	/**
	 * @param array $data
	 * @return INode
	 */
	public function getNodeFromData( array $data ): INode {
		return new RawText(
			$data['level'],
			$data['text'],
			$data['wikitext'] ?? ''
		);
	}
}
