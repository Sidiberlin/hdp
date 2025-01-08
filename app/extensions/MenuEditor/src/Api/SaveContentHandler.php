<?php

namespace MediaWiki\Extension\MenuEditor\Api;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use RequestContext;
use Wikimedia\ParamValidator\ParamValidator;

class SaveContentHandler extends MenuHandler {

	/**
	 * @return \MediaWiki\Rest\Response|mixed
	 * @throws HttpException
	 */
	public function execute() {
		$params = $this->getValidatedParams();
		$page = $this->makeTitle( $params['pagename'] );
		$body = $this->getValidatedBody();

		$parser = $this->getParserForRevision( $page );
		$parser->addNodesFromData( $body );

		$rev = $parser->saveRevision( RequestContext::getMain()->getUser() );
		if ( !$rev ) {
			throw new HttpException( 'save-failed' );
		}
		return $this->getResponseFactory()->createJson( [
			'revision' => $rev->getId(),
		] );
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'pagename' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			]
		];
	}

	/**
	 * @param string $contentType
	 * @return JsonBodyValidator
	 * @throws HttpException
	 */
	public function getBodyValidator( $contentType ) {
		if ( $contentType !== 'application/json' ) {
			throw new HttpException( 'ContentType header must be application/json' );
		}
		return new JsonBodyValidator( [] );
	}
}
