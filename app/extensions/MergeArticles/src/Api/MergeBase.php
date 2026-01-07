<?php

namespace MergeArticles\Api;

use File;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\ContentHandler;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Page\DeletePageFactory;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MWContentSerializationException;
use MWUnknownContentModelException;
use RepoGroup;
use Throwable;
use Wikimedia\ParamValidator\ParamValidator;

class MergeBase extends ApiBase {
	/** @var Title|null */
	protected ?Title $originTitle = null;
	/** @var Title|null */
	protected ?Title $targetTitle = null;
	/** @var string */
	protected string $text = '';
	/** @var Status */
	protected Status $status;

	protected $editFlag = 1;

	/** @var bool */
	protected $skipFile = false;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param HookContainer $hookContainer
	 * @param WikiPageFactory $wikiPageFactory
	 * @param RepoGroup $repoGroup
	 * @param DeletePageFactory $deletePageFactory
	 */
	public function __construct(
		ApiMain $mainModule, string $moduleName,
		private readonly HookContainer $hookContainer,
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly RepoGroup $repoGroup,
		private readonly DeletePageFactory $deletePageFactory
	) {
		parent::__construct( $mainModule, $moduleName );
	}

	/**
	 * @return void
	 * @throws ApiUsageException
	 * @throws MWContentSerializationException
	 * @throws MWUnknownContentModelException
	 */
	public function execute() {
		$this->status = Status::newGood();

		$this->readInParameters();
		if ( !$this->verifyOrigin() ) {
			$this->returnResults();
			return;
		}
		if ( !$this->verifyTarget() ) {
			$this->returnResults();
			return;
		}
		$this->verifyPermissions();

		if ( !$this->merge() ) {
			$this->returnResults();
			return;
		}
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
			],
			'text' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				static::PARAM_HELP_MSG => 'mergearticles-apihelp-param-text',
			],
			'skipFile' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => false,
				static::PARAM_HELP_MSG => 'mergearticles-apihelp-param-skipfile',
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

		$this->text = $this->getParameter( 'text' );
		$this->skipFile = $this->getParameter( 'skipFile' );
	}

	/**
	 * @return bool
	 */
	protected function verifyOrigin() {
		if ( !$this->originTitle instanceof Title || !$this->originTitle->exists() ) {
			$this->status = Status::newFatal( 'invalid-origin' );
			return false;
		}
		return true;
	}

	/**
	 * @return bool
	 */
	protected function verifyTarget() {
		if ( $this->targetTitle instanceof Title === false ) {
			$this->status = Status::newFatal( 'target-invalid' );
			return false;
		}
		return true;
	}

	/**
	 * @return bool
	 * @throws MWContentSerializationException
	 * @throws MWUnknownContentModelException
	 */
	protected function merge() {
		if ( $this->isFile() && $this->shouldMergeFile() ) {
			$this->mergeFile();
		}

		$text = $this->text;
		$targetTitle = $this->targetTitle;
		$this->hookContainer->run( 'MergeArticlesBeforeMergePage', [
			&$text,
			&$targetTitle,
			$this->originTitle
		] );
		$content = ContentHandler::makeContent( $text, $targetTitle );
		$status = null;
		try {
			$wikipage = $this->wikiPageFactory->newFromTitle( $targetTitle );
			$updater = $wikipage->newPageUpdater( $this->getUser() );
			$updater->setContent( SlotRecord::MAIN, $content );
			$updater->saveRevision(
				CommentStoreComment::newUnsavedComment( 'Merge articles' ), $this->editFlag
			);
			$status = $updater->getStatus();
		} catch ( Throwable $ex ) {
			$status = Status::newFatal( $ex->getMessage() );
		}
		if ( !$status->isOK() ) {
			$messages = $status->getMessages();
			$first = $messages[0];
			if ( $first->getKey() === 'edit-no-change' ) {
				$this->status = Status::newGood();
				return true;
			}
			$this->status = $status;
			return false;
		}

		$this->hookContainer->run( 'MergeArticlesAfterMergePage', [
			$targetTitle,
			$this->originTitle
		] );
		return true;
	}

	/**
	 * @return bool
	 */
	protected function isFile(): bool {
		if ( $this->originTitle->getNamespace() === NS_FILE ) {
			return true;
		}
		return false;
	}

	/**
	 * @return bool
	 */
	protected function mergeFile() {
		$file = $this->repoGroup->findFile( $this->originTitle );
		if ( !$file ) {
			$this->status = Status::newFatal( 'invalid-file' );
			return false;
		}

		return $this->uploadFile( $file );
	}

	/**
	 *
	 * @param File $file
	 * @return bool
	 */
	protected function uploadFile( File $file ) {
		$services = MediaWikiServices::getInstance();
		$localFileRepo = $services->getRepoGroup()->getLocalRepo();

		$uploadStash = new \UploadStash( $localFileRepo, $this->getUser() );
		$uploadFile = $uploadStash->stashFile( $file->getLocalRefPath(), "file" );
		$targetFileName = $this->targetTitle->getDBkey();

		if ( $uploadFile === false ) {
			$this->status = Status::newFatal( 'upload-file-creation-error' );
			return false;
		}

		$uploadFromStash = new \UploadFromStash( $this->getUser(), $uploadStash, $localFileRepo );
		$uploadFromStash->initialize( $uploadFile->getFileKey(), $targetFileName );
		$status = $uploadFromStash->performUpload( 'Merge articles upload', $this->text, true, $this->getUser() );
		$uploadFromStash->cleanupTempFile();

		$localFileRepo->newFile( $targetFileName );
		if ( !$status->isGood() ) {
			$messages = $status->getMessages();
			$first = $messages[0];
			if ( $first->getKey() === 'edit-no-change' ) {
				$this->status = Status::newGood();
				return true;
			}
			$this->status = Status::newFatal( 'upload-error' );
			return false;
		}
		return true;
	}

	/**
	 * @return Status
	 */
	protected function removeOrigin() {
		if ( $this->isFile() ) {
			$file = $this->repoGroup->findFile( $this->originTitle );
			$file->deleteFile( 'Article merge', $this->getUser() );
		}

		$wikipage = $this->wikiPageFactory->newFromTitle( $this->originTitle );
		$deletePage = $this->deletePageFactory->newDeletePage(
			$wikipage,
			$this->getUser()
		);

		return $deletePage
			->setSuppress( true )
			->keepLegacyHookErrorsSeparate()
			->deleteUnsafe( 'Article merge' );
	}

	/**
	 * @return void
	 */
	protected function returnResults() {
		$result = $this->getResult();

		if ( $this->status->isGood() ) {
			$targetPage = [
				'text' => $this->targetTitle->getPrefixedText(),
				'url' => $this->targetTitle->getLocalURL()
			];

			$result->addValue( null, 'success', 1 );
			$result->addValue( null, 'targetPage', $targetPage );
		} else {
			$result->addValue( null, 'success', 0 );
			$result->addValue( null, 'error', $this->status->getMessage() );
		}
	}

	/**
	 * @return bool
	 */
	private function shouldMergeFile() {
		return !$this->skipFile;
	}

	/**
	 * @return void
	 * @throws ApiUsageException
	 */
	protected function verifyPermissions() {
		if ( !$this->getUser()->isAllowed( 'merge-articles' ) ) {
			$this->dieWithError(
				Message::newFromKey( 'apierror-permissiondenied' )
					->params( 'merge-articles' )
			);
		}
	}
}
