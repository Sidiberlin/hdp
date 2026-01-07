<?php

namespace ChatBot\RunJobsTriggerHandler;

use BS\ExtendedSearch\Source\Job\UpdateJob;
use ChatBot\DeepsetApi\IndexApi;
use ChatBot\ExternalIndex\UpdateIndexTable;
use ChatBot\Interval\EveryFiveMinutes;
use Exception;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use MWStake\MediaWiki\Component\RunJobsTrigger\IHandler;
use MWStake\MediaWiki\Component\RunJobsTrigger\Interval;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Status;
use TitleFactory;
use Wikimedia\Rdbms\LoadBalancer;

class IndexDeepset implements IHandler, LoggerAwareInterface {

	public const HANDLER_KEY = 'ext-chatbot-index-pages';

	public const BMBF_INDEX_TABLE = "bmbf_index_pages";
	public const BMBF_INDEX_ACTION_FIELD = "bmbf_action";
	public const BMBF_INDEX_DATA_FIELD = "bmbf_data";
	public const BMBF_INDEX_PAGE_FIELD = "bmbf_page";

	public const BMBF_INDEX_LOGGER_CHANNEL = "Bmbf.Index";

	private const WIKIPAGE_FILE_SUFFIX = '.txt';

	private const INTRO_SECTION_NAME = 'Intro';

	private const FILE_META_STATIC_KEYS = [
		'hw-id',
		'uri',
		'basename',
		'basename_exact',
		'prefixed_title',
		'namespace',
		'namespace_text',
		'mtime',
		'ctime',
		'mime_type',
		'extension',
		'sortable_id',
		'suggestions',
	];

	private const PAGE_META_ADDITIONAL_STATIC_KEYS = [
		'display_title',
		'categories',
		'sections',
		'tags',
		'redirects_to',
		'redirected_from',
		'page_language',
		'books',
		'suggestions_extra',
		'chatbotmeta'
	];

	/** @var LoggerInterface */
	private LoggerInterface $logger;

	/** @var TitleFactory */
	private TitleFactory $titleFactory;

	/** @var LoadBalancer */
	private LoadBalancer $loadBalancer;

	/** @var WikiPageFactory */
	private WikiPageFactory $wikiPageFactory;

	/** @var IndexApi */
	private IndexApi $indexApi;

	/**
	 * @param IndexApi $indexApi
	 * @param LoadBalancer $loadBalancer
	 * @param TitleFactory $titleFactory
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		IndexApi $indexApi,
		LoadBalancer $loadBalancer,
		TitleFactory $titleFactory,
		WikiPageFactory $wikiPageFactory
	) {
		$this->logger = LoggerFactory::getInstance( self::BMBF_INDEX_LOGGER_CHANNEL );
		$this->titleFactory = $titleFactory;
		$this->loadBalancer = $loadBalancer;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->indexApi = $indexApi;
	}

	/**
	 *
	 * @return Interval
	 */
	public function getInterval() {
		return new EveryFiveMinutes();
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return static::HANDLER_KEY;
	}

	/**
	 * @param LoggerInterface $logger
	 *
	 * @return void
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	public function run() {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		$indexData = $db->select(
			self::BMBF_INDEX_TABLE,
			[
				self::BMBF_INDEX_PAGE_FIELD,
				self::BMBF_INDEX_ACTION_FIELD,
				self::BMBF_INDEX_DATA_FIELD
			],
		);

		// Files are being deleted in one request
		$toDelete = [];

		$this->logger->info( 'Indexing ' . $indexData->numRows() . ' pages' );

		foreach ( $indexData as $row ) {
			$dbKey = $row->{self::BMBF_INDEX_PAGE_FIELD};
			$action = $row->{self::BMBF_INDEX_ACTION_FIELD};
			$mappedFields = unserialize( $row->{self::BMBF_INDEX_DATA_FIELD} );

			$this->logger->info(
				'Processing ' . json_encode( [
					'dbKey' => $dbKey,
					'action' => $action
				] )
			);

			try {
				$this->validate( $mappedFields, $action );
				$status = Status::newGood();
			} catch ( Exception $e ) {
				$this->logger->error( $e->getMessage() );
				continue;
			}

			if ( $action === UpdateJob::ACTION_DELETE ) {
				$toDelete[] = $mappedFields['prefixed_title'];

				continue;
			}

			if ( $action === UpdateJob::ACTION_UPDATE ) {
				try {
					$status = $this->upsert( $dbKey, $mappedFields );
				} catch ( Exception $e ) {
					$status = Status::newFatal( $e->getMessage() );
				}
			}

			if ( !$status->isGood() ) {
				$this->logger->error(
					'Error indexing ' . json_encode( [
						'status' => $status->getMessage()->text(),
						'action' => $action,
						'data' => $mappedFields
					] )
				);
			}
		}

		$this->logger->info( 'Deleting ' . count( $toDelete ) . ' pages from index' );
		$status = $this->indexApi->batchDelete( $toDelete );
		if ( !$status->isGood() ) {
			$this->logger->error(
				'Error deleting ' . json_encode( [
					'status' => $status->getMessage()->text(),
					'action' => UpdateJob::ACTION_DELETE,
					'data' => $toDelete
				] )
			);
		}

		// Clear the table
		$this->logger->info( "Clearing index table" );
		$db->delete( self::BMBF_INDEX_TABLE, '*' );

		return Status::newGood();
	}

	/**
	 * Creates txt file in Deepset with given content
	 *
	 * @param string $dbKey
	 * @param array $mappedFields
	 *
	 * @return Status
	 * @throws Exception
	 */
	private function upsert( string $dbKey, array $mappedFields ): Status {
		$sourceKey = $mappedFields['sourcekey'];

		if ( $sourceKey === UpdateIndexTable::SOURCEKEY_REPOFILE ) {
			return $this->indexApi->pushFile(
				$mappedFields['source_file_path'],
				$this->createFileMetaData( $mappedFields )
			);
		}

		if ( $sourceKey === UpdateIndexTable::SOURCEKEY_WIKIPAGE ) {
			$title = $this->titleFactory->newFromText( $mappedFields['prefixed_title'] );
			$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
			$output = $wikiPage->getParserOutput();

			if ( !$output ) {
				return Status::newFatal( 'No output' );
			}

			$htmlText = $output->getText(
				[
					'allowTOC' => false,
					'injectTOC' => '',
					'enableSectionEditLinks' => false,
					'unwrap' => true
				]
			);

			if ( empty( $htmlText ) ) {
				throw new Exception( 'Empty content' );
			}

			$metadata = $this->createPageMetaData( $title, $output, $mappedFields );

			// First delete pages by prefixed title
			$status = $this->indexApi->batchDelete( [ $mappedFields['prefixed_title'] ] );

			if ( !$status->isGood() ) {
				return $status;
			}

			// No sections defined, index the whole page as one document
			if ( empty( $mappedFields['sections'] ) ) {
				return $this->indexApi->pushPage(
					$this->getWikiPageIndexName( $dbKey ),
					trim( strip_tags( $htmlText ) ),
					$metadata
				);
			}

			$sections = $this->getRawPageContentBySections(
				$htmlText,
				$mappedFields['sections']
			);

			foreach ( $sections as $sectionName => $content ) {
				if ( empty( $content ) ) {
					throw new Exception( 'Empty content' );
				}

				// Replace the sections with the section name
				$metadata['sections'] = [ $sectionName ];
				$status = $this->indexApi->pushPage(
					$this->getWikiPageIndexName( $dbKey, $sectionName ),
					$content,
					$metadata
				);

				if ( !$status->isGood() ) {
					return $status;
				}
			}
		}

		return Status::newGood();
	}

	/**
	 * @param string $dbKey
	 * @param string|null $sectionName
	 *
	 * @return string
	 */
	private function getWikiPageIndexName( string $dbKey, ?string $sectionName = null ): string {
		$dbKey = trim( trim( $dbKey ), "." );

		if ( !$sectionName ) {
			return $dbKey . self::WIKIPAGE_FILE_SUFFIX;
		}

		$sectionName = trim( trim( $sectionName ), "." );

		return $dbKey . '_' . $sectionName . self::WIKIPAGE_FILE_SUFFIX;
	}

	/**
	 * Splits the page content by sections
	 * Converts the Wikitext content to plain text
	 *
	 * @param string $htmlText
	 * @param array $sections
	 *
	 * @return array
	 * @throws Exception
	 */
	private function getRawPageContentBySections( string $htmlText, array $sections ): array {
		$contents = [];

		// Get the text before the first heading (if any)
		if ( preg_match( '/^(.*?)\s*(?=<h[1-6]>)/si', $htmlText, $matches ) ) {
			$content = trim( strip_tags( $matches[1] ) );
			if ( !empty( $content ) ) {
				$contents[self::INTRO_SECTION_NAME] = $content;
			}
		}

		$pattern = '/(<h[1-6]>.*?<\/h[1-6]>\s*.*?)(?=(<h[1-6]>.*?<\/h[1-6]>)|$)/si';
		preg_match_all( $pattern, $htmlText, $matches );

		if ( count( $matches[0] ) !== count( $sections ) ) {
			throw new Exception( 'Sections do not match' );
		}

		foreach ( $matches[0] as $key => $match ) {
			$contents[$sections[$key]] = trim( strip_tags( $match ) );
		}

		return $contents;
	}

	/**
	 * see UpdateIndexTable.php for the mapping
	 *
	 * @param Title $title
	 * @param ParserOutput $output
	 * @param array $mappedFields
	 *
	 * @return array
	 */
	private function createPageMetaData( Title $title, ParserOutput $output, array $mappedFields ): array {
		$meta = [];
		foreach ( array_merge( self::FILE_META_STATIC_KEYS, self::PAGE_META_ADDITIONAL_STATIC_KEYS ) as $key ) {
			if ( !isset( $mappedFields[$key] ) ) {
				continue;
			}

			$meta[$key] = $mappedFields[$key];
		}

		$meta['title'] = $title->getSubpageText();

		// Add links
		$meta['attachments'] = $this->createLinkList( $output );

		// Add parent pages
		if ( $title->isSubpage() ) {
			$parentPages = explode( "/", $title->getBaseText() );
			foreach ( $parentPages as $key => $parentPage ) {
				$meta['title_level_' . ( $key + 1 )] = $parentPage;
			}
		}

		return $meta;
	}

	/**
	 * see UpdateIndexTable.php for the mapping
	 *
	 * @param array $mappedFields
	 *
	 * @return array
	 */
	private function createFileMetaData( array $mappedFields ): array {
		$meta = [];
		foreach ( self::FILE_META_STATIC_KEYS as $key ) {
			if ( !isset( $mappedFields[$key] ) ) {
				continue;
			}

			$meta[$key] = $mappedFields[$key];
		}

		return $meta;
	}

	/**
	 * @param ParserOutput $output
	 *
	 * @return array
	 */
	private function createLinkList( ParserOutput $output ): array {
		$internalLinks = [];
		$externalLinks = array_keys($output->getExternalLinks());

		foreach ( $output->getLinkList(ParserOutputLinkTypes::LOCAL) as $linkListItem ) {
			/** @var TitleValue $linkTarget */
			$linkTarget = $linkListItem['link'];
			$title = Title::castFromLinkTarget($linkTarget);

			if ( !$title ) {
				continue;
			}

			$internalLinks[] = $title->getFullURL();
		}

		foreach ( $output->getLinkList(ParserOutputLinkTypes::MEDIA) as $linkListItem ) {
			/** @var TitleValue $linkTarget */
			$linkTarget = $linkListItem['link'];
			$title = Title::castFromLinkTarget($linkTarget);

			if ( !$title ) {
				continue;
			}

			$internalLinks[] = $title->getFullURL();
		}

		return array_merge( $internalLinks, $externalLinks );
	}

	/**
	 * @param array $mappedFields
	 * @param string $action
	 *
	 * @throws Exception
	 */
	private function validate( array $mappedFields, string $action ): void {
		if ( $action !== UpdateJob::ACTION_DELETE && $action !== UpdateJob::ACTION_UPDATE ) {
			throw new Exception(
				'Unknown action ' . json_encode( [
					'action' => $action,
					'data' => $mappedFields
				] )
			);
		}

		if ( !isset( $mappedFields['sourcekey'] ) ) {
			throw new Exception(
				'Missing source key ' . json_encode( [
					'action' => $action,
					'data' => $mappedFields
				] )
			);
		}

		if ( !isset( $mappedFields['prefixed_title'] ) ) {
			throw new Exception(
				'Missing prefixed title ' . json_encode( [
					'action' => $action,
					'data' => $mappedFields
				] )
			);
		}
	}
}
