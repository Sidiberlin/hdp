<?php

namespace BlueSpice\Bookshelf\Rest;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\JsonContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Json\FormatJson;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class PostBookMetadata extends SimpleHandler {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/**
	 *
	 * @param TitleFactory $titleFactory
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		TitleFactory $titleFactory, WikiPageFactory $wikiPageFactory
	) {
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function run() {
		$validated = $this->getValidatedParams();
		$body = $this->getValidatedBody();
		$bookName = $validated[ 'booktitle' ];
		$bookTitle = $this->titleFactory->newFromText( $bookName );
		if ( !$bookTitle ) {
			return $this->getResponseFactory()->createHttpError( 404, [ 'No valid book title' ] );
		}

		$user = RequestContext::getMain()->getUser();

		$meta = $body['meta'];
		if ( !$meta ) {
			return $this->getResponseFactory()->createHttpError( 404, [ 'No valid meta data' ] );
		}
		$content = new JsonContent( FormatJSON::encode( $meta ) );

		$wikiPage = $this->wikiPageFactory->newFromTitle( $bookTitle );
		$pageUpdater = $wikiPage->newPageUpdater( $user );
		$pageUpdater->setContent( 'book_meta', $content );
		if ( $bookTitle->exists() === false ) {
			$content = $wikiPage->getContentHandler()->makeEmptyContent();
			$pageUpdater->setContent( SlotRecord::MAIN, $content );
		}
		$comment = CommentStoreComment::newUnsavedComment( '' );
		$revisionRecord = $pageUpdater->saveRevision( $comment );

		$status = 'error';
		if ( $revisionRecord ) {
			$status = 'success';
		}

		return $this->getResponseFactory()->createJson(
			[ 'status' => $status, 'book' => $bookTitle->getPrefixedText() ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		return [
			'booktitle' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	public function getBodyParamSettings(): array {
		return [
			'meta' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => []
			],
		];
	}
}
