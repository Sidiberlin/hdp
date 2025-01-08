<?php

namespace ChatBot\ExternalIndex;

use BS\ExtendedSearch\ExternalIndex;
use BS\ExtendedSearch\Source\Job\UpdateJob;
use ChatBot\RunJobsTriggerHandler\IndexDeepset;
use Config;
use Exception;
use MediaWiki\MediaWikiServices;
use Status;
use Title;
use TitleFactory;
use Wikimedia\Rdbms\ILoadBalancer;

class UpdateIndexTable extends ExternalIndex {

	/** @var string */
	public const SOURCEKEY_WIKIPAGE = 'wikipage';

	/** @var string */
	public const SOURCEKEY_REPOFILE = 'repofile';

	/** @var ILoadBalancer */
	private ILoadBalancer $lb;

	/** @var TitleFactory */
	private TitleFactory $titleFactory;

	/** @var array */
	private array $supportedFileExtensions;

	/** @var int[] */
	private array $supportedNamespaces;

	/**
	 * @param MediaWikiServices $services
	 * @param Config $config
	 * @param array $document
	 */
	public function __construct( MediaWikiServices $services, Config $config, array $document ) {
		parent::__construct( $services, $config, $document );

		$this->lb = $services->getDBLoadBalancer();
		$this->titleFactory = $services->getTitleFactory();
		$this->supportedFileExtensions = $config->get( 'BmbfDeepsetIndexSupportedFileExtensions' );
		$this->supportedNamespaces = $services->getNamespaceInfo()->getContentNamespaces();
	}

	/**
	 * Inserts a new record into the bmbf_index_pages table
	 * for updating or deleting pages in deepset index later
	 *
	 * @param array $mappedFields
	 * @param string $action
	 *
	 * @return Status
	 */
	protected function doPush( array $mappedFields, $action ): Status {
		if ( $action !== UpdateJob::ACTION_DELETE && $action !== UpdateJob::ACTION_UPDATE ) {
			return Status::newFatal( 'Unknown action' );
		}

		// Add freetext meta field from smw property
		if ( isset( $mappedFields['smwproperty'] ) ) {
			$key = array_search( 'Chatbotmeta', array_column( $mappedFields['smwproperty'], 'name' ) );
			if ( $key !== false ) {
				$property = $mappedFields['smwproperty'][$key];
				$mappedFields['chatbotmeta'] = $property['value'];
			}
			unset( $mappedFields['smwproperty'] );
		}

		// Convert date fields from timestamp to ISO 8601 for files
		if ( $mappedFields['sourcekey'] === self::SOURCEKEY_REPOFILE ) {
			if ( isset( $mappedFields['ctime'] ) ) {
				$mappedFields['ctime'] = gmdate( "Y-m-d\TH:i:s\Z", $mappedFields['ctime'] );
			}
			if ( isset( $mappedFields['mtime'] ) ) {
				$mappedFields['mtime'] = gmdate( "Y-m-d\TH:i:s\Z", $mappedFields['mtime'] );
			}
		}

		// On repofile prefixed_title is file namespace and basename_exact
		if ( $mappedFields['sourcekey'] === self::SOURCEKEY_REPOFILE && !isset( $mappedFields['prefixed_title'] ) ) {
			$name = $mappedFields['basename_exact'];

			if ( $mappedFields['namespace'] !== NS_MAIN ) {
				$name = $mappedFields['namespace_text'] . ':' . $name;
			}

			$title = $this->titleFactory->makeTitle( NS_FILE, $name );
			$mappedFields['prefixed_title'] = $title->getPrefixedText();
		} else {
			$title = $this->titleFactory->newFromText( $mappedFields['prefixed_title'] );
		}

		// Delete redirect pages, instead of update them
		if ( !empty( $mappedFields['redirects_to'] ) ) {
			$action = UpdateJob::ACTION_DELETE;
		}

		try {
			$this->validate( $mappedFields );
		} catch ( Exception $e ) {
			return Status::newGood( $e->getMessage() );
		}

		$data = serialize( $mappedFields );

		$db = $this->lb->getConnection( DB_PRIMARY );
		$db->upsert( IndexDeepset::BMBF_INDEX_TABLE, [
			IndexDeepset::BMBF_INDEX_PAGE_FIELD => $title->getPrefixedDBkey(),
			IndexDeepset::BMBF_INDEX_ACTION_FIELD => $action,
			IndexDeepset::BMBF_INDEX_DATA_FIELD => $data,
		], IndexDeepset::BMBF_INDEX_PAGE_FIELD, [
			IndexDeepset::BMBF_INDEX_ACTION_FIELD => $action,
			IndexDeepset::BMBF_INDEX_DATA_FIELD => $data,
		] );

		return Status::newGood();
	}

	/**
	 * Don`t index if
	 * - no sourcekey provided
	 * - no prefixed title provided
	 * - namespace is not supported
	 * - page is a file page
	 *
	 * @param array $mappedFields
	 *
	 * @throws Exception
	 */
	private function validate( array $mappedFields ): void {
		if ( !isset( $mappedFields['sourcekey'] ) ) {
			throw new Exception( 'No sourcekey provided' );
		}

		if ( !isset( $mappedFields['prefixed_title'] ) ) {
			throw new Exception( 'Missing prefixed title' );
		}

		// Skip files with unsupported extensions
		if ( !$this->isFileExtensionSupported( $mappedFields ) ) {
			throw new Exception( 'Skipping file extension' );
		}

		$title = $this->titleFactory->newFromDBkey( $mappedFields['prefixed_title'] );

		// Skip namespaces that should not be indexed
		if ( !$this->isNamespaceSupported( $title ) ) {
			throw new Exception( 'Skipping namespace' );
		}

		// Skip file pages, only index the file itself
		if ( $this->isFilePage( $title, $mappedFields ) ) {
			throw new Exception( 'Skipping file page' );
		}
	}

	/**
	 * Deepset Cloud supports .txt, .csv, .json, .xml, .html, .md, .pdf, .docx, .pptx and .xlsx files.
	 *
	 * @see https://docs.cloud.deepset.ai/reference/upload_file_api_v1_workspaces__workspace_name__files_post
	 *
	 * @param array $mappedFields
	 *
	 * @return bool
	 */
	private function isFileExtensionSupported( array $mappedFields ): bool {
		if ( $mappedFields['sourcekey'] !== self::SOURCEKEY_REPOFILE ) {
			return true;
		}

		$extension = $mappedFields['extension'];

		if ( !$extension ) {
			$extension = pathinfo( $mappedFields['prefixed_title'], PATHINFO_EXTENSION );
		}

		if ( !$extension ) {
			return false;
		}

		return in_array( $extension, $this->supportedFileExtensions, true );
	}

	/**
	 * Skip namespaces that should not be indexed
	 *
	 * @param Title $title
	 *
	 * @return bool
	 */
	private function isNamespaceSupported( Title $title ): bool {
		if ( $title->isSpecialPage() ) {
			return false;
		}

		if ( $title->getNamespace() === NS_FILE ) {
			return true;
		}

		return in_array( $title->getNamespace(), $this->supportedNamespaces, true );
	}

	/**
	 * @param Title $title
	 * @param array $mappedFields
	 *
	 * @return bool
	 */
	private function isFilePage( Title $title, array $mappedFields ): bool {
		if ( $mappedFields['sourcekey'] !== self::SOURCEKEY_WIKIPAGE ) {
			return false;
		}

		return $title->getNamespace() === NS_FILE;
	}

	/**
	 * @inheritDoc
	 */
	public function getMapping() {
		return [
			'id' => 'hw-id',
			'uri' => 'uri',
			'basename' => 'basename',
			'basename_exact' => 'basename_exact',
			'prefixed_title' => 'prefixed_title',
			'display_title' => 'display_title',
			'namespace' => 'namespace',
			'smwproperty' => 'smwproperty',
			'namespace_text' => 'namespace_text',
			'mtime' => 'mtime',
			'ctime' => 'ctime',
			'categories' => 'categories',
			'sections' => 'sections',
			'tags' => 'tags',
			'redirects_to' => 'redirects_to',
			'redirected_from' => 'redirected_from',
			'page_language' => 'page_language',
			'books' => 'books',
			'source_file_path' => 'source_file_path',
			'mime_type' => 'mime_type',
			'extension' => 'extension',
			'sortable_id' => 'sortable_id',
			'suggestions' => 'suggestions',
			'suggestions_extra' => 'suggestions_extra',
			'sourcekey' => 'sourcekey'
		];
	}
}
