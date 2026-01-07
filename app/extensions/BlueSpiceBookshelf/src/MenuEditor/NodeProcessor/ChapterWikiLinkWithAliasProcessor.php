<?php

namespace BlueSpice\Bookshelf\MenuEditor\NodeProcessor;

use BlueSpice\Bookshelf\MenuEditor\Node\ChapterWikiLinkWithAlias;
use MediaWiki\Extension\MenuEditor\NodeProcessor\MenuNodeProcessor;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\Wikitext\NodeSource\WikitextSource;
use MWStake\MediaWiki\Lib\Nodes\INode;
use MWStake\MediaWiki\Lib\Nodes\INodeSource;

class ChapterWikiLinkWithAliasProcessor extends MenuNodeProcessor {
	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( TitleFactory $titleFactory ) {
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @param string $wikitext
	 * @return bool
	 */
	public function matches( $wikitext ): bool {
		return (bool)preg_match( '/^(\*{1,})\s{0,}\[\[(.*?)\]\]$/', $wikitext );
	}

	/**
	 * @param INodeSource|WikitextSource $source
	 * @return INode
	 */
	public function getNode( INodeSource $source ): INode {
		$link = $this->getNodeValue( $source->getWikitext() );
		$stripped = trim( $link, '[]' );
		$bits = explode( '|', $stripped );
		$target = array_shift( $bits );
		$label = !empty( $bits ) ? array_shift( $bits ) : '';

		return new ChapterWikiLinkWithAlias(
			$target,
			$label,
			$source->getWikitext(),
			$this->titleFactory,
			$this->getLevel( $source->getWikitext() ),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function supportsNodeType( $type ): bool {
		return $type === 'bs-bookshelf-chapter-wikilink-with-alias';
	}

	/**
	 * @param array $data
	 * @return INode
	 */
	public function getNodeFromData( array $data ): INode {
		return new ChapterWikiLinkWithAlias(
			$data['target'],
			$data['label'],
			$data['wikitext'] ?? '',
			$this->titleFactory,
			$data['level'],
		);
	}
}
