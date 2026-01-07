<?php

namespace MergeArticles\Api;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class MergeNewPage extends MergeBase {

	/**
	 *
	 * @return array
	 */
	protected function getAllowedParams() {
		return parent::getAllowedParams() + [
			'target' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				static::PARAM_HELP_MSG => 'mergearticles-apihelp-param-target',
			],
		];
	}

	/**
	 * @return void
	 * @throws ApiUsageException
	 */
	protected function readInParameters() {
		parent::readInParameters();

		$targetText = $this->getParameter( 'target' );
		$this->targetTitle = Title::newFromText( $targetText );
		// Cannot skip file on new page
		$this->skipFile = false;
	}

	/**
	 * This might be an overkill, because we have target from the params,
	 * and we calculate it here
	 *
	 * @return bool
	 */
	protected function verifyTarget() {
		if ( $this->originTitle->getNamespace() === NS_DRAFT ) {
			$target = Title::newFromText( $this->originTitle->getDBkey() );
		} elseif ( $this->originTitle->getNamespace() === NS_FILE ) {
			$draftFilePrefix = $this->getConfig()->get( 'MADraftFilePrefix' );
			$stripped = str_replace( $draftFilePrefix, '', $this->originTitle->getDBkey() );
			$target = Title::makeTitle( NS_FILE, $stripped );
		}

		if ( !$target instanceof Title || !$this->targetTitle instanceof Title ) {
			$this->status = Status::newFatal( 'invalid-target' );
			return false;
		}
		if ( $this->targetTitle->equals( $target ) === false ) {
			$this->status = Status::newFatal( 'target-mismatch' );
			return false;
		}
		if ( $this->targetTitle->exists() ) {
			$this->status = Status::newFatal( 'target-exists' );
			return false;
		}
		return true;
	}

	/**
	 * @return bool
	 */
	protected function mergeFile() {
		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $this->targetTitle );
		if ( $file ) {
			$this->status = Status::newFatal( 'target-file-exists' );
			return false;
		}

		return parent::mergeFile();
	}
}
