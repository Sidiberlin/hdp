<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Reader\Tag;

use DOMNode;

class BookmarkEnd extends TagProcessorBase {

	/**
	 * @inheritDoc
	 */
	public function process( DOMNode $node ): string {
		// There are two XML tags for bookmarks, used in DOCX XML: <bookmarkStart> and <bookmarkEnd>
		// We use <bookmarkStart> to place "anchor" link in document.
		// But we actually don't need <bookmarkEnd>, so it can be just removed.
		// Nothing to do here.
		return "";
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return 'bookmarkEnd';
	}

	/**
	 * @inheritDoc
	 */
	public function getNamespace(): string {
		return 'w';
	}
}
