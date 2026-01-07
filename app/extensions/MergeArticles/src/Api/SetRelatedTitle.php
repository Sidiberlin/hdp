<?php

namespace MergeArticles\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class SetRelatedTitle extends ApiBase {
	protected $targetTitle;
	protected $relatedTo;

	/** @var Status */
	protected $status;

	public function execute() {
		$this->status = Status::newGood();

		$this->readInParameters();
		$this->setPageProps();
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
			],
			'relatedTo' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				static::PARAM_HELP_MSG => 'mergearticles-apihelp-param-relatedto',
			]
		];
	}

	protected function readInParameters() {
		$pageID = $this->getParameter( 'pageID' );
		$this->targetTitle = Title::newFromID( $pageID );

		$this->relatedTo = $this->getParameter( 'relatedTo' );
	}

	protected function setPageProps() {
		if ( !$this->targetTitle instanceof Title || !$this->targetTitle->exists() ) {
			$this->status = Status::newFatal( 'invalid-origin' );
			return false;
		}
		if ( !$this->getUser()->isAllowed( 'merge-articles' ) ) {
			$this->status = Status::newFatal( 'permissiondenied' );
			return false;
		}
		$relatedTitle = Title::newFromID( $this->relatedTo );
		if ( !$relatedTitle instanceof Title || !$relatedTitle->exists() ) {
			$this->status = Status::newFatal( 'invalid-related-title' );
			return false;
		}
		$db = $this->getDB();
		if ( $db->selectRow(
			'pageprops',
			[ '*' ],
			[
				'pp_page' => $this->targetTitle->getArticleID(),
				'pp_propname' => 'relatedto'
			],
			__METHOD__
		) ) {
			if ( !$db->update( 'pageprops', [
				'pp_page' => $this->targetTitle->getArticleID(),
				'pp_propname' => 'relatedto',
				'pp_value' => $relatedTitle->getArticleID()
			], [
				'pp_page' => $this->targetTitle->getArticleID(),
				'pp_propname' => 'relatedto'
			] ) ) {
				$this->status = Status::newFatal( 'db-error' );
				return false;
			}
		} else {
			if ( !$db->insert( 'pageprops', [
				'pp_page' => $this->targetTitle->getArticleID(),
				'pp_propname' => 'relatedto',
				'pp_value' => $relatedTitle->getArticleID()
			] ) ) {
				$this->status = Status::newFatal( 'db-error' );
				return false;
			}
		}
		return true;
	}

	protected function returnResults() {
		$result = $this->getResult();

		if ( $this->status->isGood() ) {
			$result->addValue( null, 'success', 1 );
		} else {
			$result->addValue( null, 'success', 0 );
			$result->addValue( null, 'error', $this->status->getMessage() );
		}
	}
}
