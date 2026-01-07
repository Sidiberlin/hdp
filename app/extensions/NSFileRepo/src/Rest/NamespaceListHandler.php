<?php

namespace MediaWiki\Extension\NSFileRepo\Rest;

use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\NSFileRepo\NamespaceList;
use MediaWiki\Language\Language;
use MediaWiki\Rest\SimpleHandler;

class NamespaceListHandler extends SimpleHandler {

	/**
	 * @param Language $language
	 */
	public function __construct(
		private readonly Language $language
	) {
	}

	/**
	 * @return \MediaWiki\Rest\Response|mixed
	 */
	public function execute() {
		$list = new NamespaceList(
			RequestContext::getMain()->getUser(),
			new GlobalVarConfig( 'egNSFileRepo' ),
			$this->language
		);

		return $this->getResponseFactory()->createJson(
			[
				'read' => array_keys( $list->getReadable() ),
				'edit' => array_keys( $list->getEditable() )
			]
		);
	}

	/**
	 * @return true
	 */
	public function needsReadAccess() {
		return true;
	}

	/**
	 * @return false
	 */
	public function needsWriteAccess() {
		return false;
	}
}
