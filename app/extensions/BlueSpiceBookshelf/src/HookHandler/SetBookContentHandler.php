<?php

namespace BlueSpice\Bookshelf\HookHandler;

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\Title\Title;

class SetBookContentHandler {

	/**
	 * @param Title $title
	 * @param string &$model
	 *
	 * @return bool
	 */
	public function onContentHandlerDefaultModelFor( $title, &$model ) {
		$namespace = $title->getNamespace();

		// By namespace
		if ( $namespace === NS_BOOK ) {
			$model = 'book';
			return false;
		}
		return true;
	}

	/**
	 * @param MediaWikiServices $services
	 *
	 * @return bool|void
	 */
	public function onMediaWikiServices( $services ) {
		$services->addServiceManipulator(
			'SlotRoleRegistry',
			static function (
				SlotRoleRegistry $registry
			) {
				if ( !$registry->isDefinedRole( 'book_meta' ) ) {
					$options = [ 'display' => 'none' ];
					if ( RequestContext::getMain()->getRequest()->getBool( 'debug' ) ) {
						$options['display'] = 'section';
					}
					$registry->defineRoleWithModel( 'book_meta', CONTENT_MODEL_JSON, $options );
				}
			}
		);
	}
}
