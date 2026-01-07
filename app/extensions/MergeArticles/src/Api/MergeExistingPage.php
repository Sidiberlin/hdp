<?php

namespace MergeArticles\Api;

use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class MergeExistingPage extends MergeBase {
	protected $editFlag = 2;

	/**
	 *
	 * @return array
	 */
	protected function getAllowedParams() {
		return parent::getAllowedParams() + [
			'targetID' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				static::PARAM_HELP_MSG => 'mergearticles-apihelp-param-targetid',
			],
		];
	}

	protected function readInParameters() {
		parent::readInParameters();

		$targetID = $this->getParameter( 'targetID' );
		$this->targetTitle = Title::newFromID( $targetID );
	}

	protected function verifyTarget() {
		if ( $this->targetTitle->exists() === false ) {
			$this->status = Status::newFatal( 'target-does-not-exist' );
			return false;
		}
		return true;
	}
}
