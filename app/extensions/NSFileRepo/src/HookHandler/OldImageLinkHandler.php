<?php

namespace MediaWiki\Extension\NSFileRepo\HookHandler;

use MediaWiki\Linker\Hook\HtmlPageLinkRendererBeginHook;

class OldImageLinkHandler implements HtmlPageLinkRendererBeginHook {

	/**
	 * Rewrite the oldimage query parameter for revert links to include
	 * the title’s prefixed DBKey, ensuring it matches the DB.
	 * 20250819075637!Cat.png => 20250819075637!Animal:Cat.png
	 * @inheritDoc
	 */
	public function onHtmlPageLinkRendererBegin( $linkRenderer, $target, &$text, &$customAttribs, &$query, &$ret ) {
		if (
			isset( $query['oldimage'] ) &&
			isset( $query['action'] ) &&
			$query['action'] === 'revert' &&
			$target->inNamespace( NS_FILE )
		) {
			$oldimage = $query['oldimage'];
			$prefixed = $target->getText();

			// TIMESTAMP!FILENAME
			$parts = explode( '!', $oldimage, 2 );
			if ( count( $parts ) === 2 ) {
				[ $timestamp, $name ] = $parts;
				$query['oldimage'] = "$timestamp!$prefixed";
			}
		}

		return true;
	}

}
