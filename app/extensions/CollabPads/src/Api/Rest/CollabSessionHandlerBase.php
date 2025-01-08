<?php

namespace MediaWiki\Extension\CollabPads\Api\Rest;

use MediaWiki\Extension\CollabPads\CollabSessionManager;
use MediaWiki\Rest\SimpleHandler;

class CollabSessionHandlerBase extends SimpleHandler {

	/**
	 * @var CollabSessionManager
	 */
	protected $collabSessionManager = null;

	/**
	 * @param CollabSessionManager $collabSessionManager
	 */
	public function __construct( CollabSessionManager $collabSessionManager ) {
		$this->collabSessionManager = $collabSessionManager;
	}

	/**
	 * @param string $pageTitle
	 * @return string
	 */
	protected function unmaskPageTitle( $pageTitle ) {
		$pageTitle = str_replace( '|', '/', $pageTitle );
		return $pageTitle;
	}
}
