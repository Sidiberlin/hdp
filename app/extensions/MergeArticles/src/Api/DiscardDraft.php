<?php

namespace MergeArticles\Api;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class DiscardDraft extends MergeBase {

	/**
	 * @return void
	 */
	public function execute() {
		$this->status = Status::newGood();

		$this->readInParameters();
		if ( !$this->verifyOrigin() ) {
			$this->returnResults();
			return;
		}
		$this->verifyPermissions();
		$this->removeOrigin();
		$this->returnResults();
	}

	/**
	 *
	 * @return array
	 */
	protected function getAllowedParams() {
		return [
			'pageID' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				static::PARAM_HELP_MSG => 'mergearticles-apihelp-param-pageid',
			]
		];
	}

	/**
	 * @return void
	 * @throws ApiUsageException
	 */
	protected function readInParameters() {
		$pageID = $this->getParameter( 'pageID' );
		$this->originTitle = Title::newFromID( $pageID );
	}

	/**
	 * @return void
	 */
	protected function returnResults() {
		$result = $this->getResult();
		if ( $this->status->isGood() ) {
			$result->addValue( null, 'success', 1 );
			$result->addValue( null, 'title', $this->originTitle->getPrefixedText() );
		} else {
			$result->addValue( null, 'success', 0 );
			$result->addValue( null, 'error', $this->status->getMessage() );
		}
	}
}
