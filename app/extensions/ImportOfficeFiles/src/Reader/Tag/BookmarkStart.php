<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag;

use DOMNode;

class BookmarkStart extends TagProcessorBase {

	/**
	 * @inheritDoc
	 */
	public function process( DOMNode $node ): string {
		$name = $node->getAttribute( 'w:name' );
		return '<span class="bookmark-start" id="' . $name . '"></span>';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return 'bookmarkStart';
	}

	/**
	 * @inheritDoc
	 */
	public function getNamespace(): string {
		return 'w';
	}
}
