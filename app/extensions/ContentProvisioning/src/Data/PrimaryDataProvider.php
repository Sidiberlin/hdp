<?php

namespace MediaWiki\Extension\ContentProvisioning\Data;

use MediaWiki\Content\TextContent;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use MWStake\MediaWiki\Component\ContentProvisioner\ImportLanguage;
use MWStake\MediaWiki\Component\ContentProvisioner\ManifestListProvider\StaticManifestProvider;
use MWStake\MediaWiki\Component\DataStore\IPrimaryDataProvider;

class PrimaryDataProvider implements IPrimaryDataProvider {

	/**
	 * @var WikiPageFactory
	 */
	private $wikiPageFactory;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * @var Language
	 */
	private $wikiLang;

	/**
	 * @var LanguageFallback
	 */
	private $languageFallback;

	/**
	 * @param WikiPageFactory $wikiPageFactory
	 * @param TitleFactory $titleFactory
	 * @param Language $wikiLang
	 * @param LanguageFallback $languageFallback
	 */
	public function __construct(
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		Language $wikiLang,
		LanguageFallback $languageFallback
	) {
		$this->wikiPageFactory = $wikiPageFactory;
		$this->titleFactory = $titleFactory;
		$this->wikiLang = $wikiLang;
		$this->languageFallback = $languageFallback;
	}

	/**
	 * @inheritDoc
	 */
	public function makeData( $params ) {
		$data = [];

		$enabledExtensions = array_keys( ExtensionRegistry::getInstance()->getAllThings() );

		$manifestListProvider = new StaticManifestProvider( $enabledExtensions, $GLOBALS['IP'] );

		$wikiPageManifests = $manifestListProvider->provideManifests( 'DefaultContentProvisioner' );

		$pages = [];
		foreach ( $wikiPageManifests as $absoluteManifestPath ) {
			$pagesList = json_decode( file_get_contents( $absoluteManifestPath ), true );

			$availableLanguages = [];
			foreach ( $pagesList as $titleKey => $pageData ) {
				$availableLanguages[$pageData['lang']] = true;
			}

			$importLanguage = new ImportLanguage( $this->languageFallback, $this->wikiLang->getCode() );
			$importLanguageCode = $importLanguage->getImportLanguage(
				array_keys( $availableLanguages )
			);

			foreach ( $pagesList as $titleKey => $pageData ) {
				if ( $pageData['lang'] !== $importLanguageCode ) {
					continue;
				}

				$prefixedDbKey = $pageData['target_title'];

				$pages[$prefixedDbKey]['sha1'] = $pageData['sha1'];
			}
		}

		foreach ( $pages as $prefixedDbKey => $pageData ) {
			$title = $this->titleFactory->newFromDBkey( $prefixedDbKey );

			$inSync = true;
			if ( !$title->exists() ) {
				$inSync = false;
			}

			$latestRevSha1 = $this->getContentHash( $title );
			if ( $latestRevSha1 !== $pageData['sha1'] ) {
				$inSync = false;
			}

			$recordData = [
				Record::PAGE_PREFIXED_TEXT => $prefixedDbKey,
				Record::IN_SYNC => $inSync,
				Record::CLASSES => []
			];

			if ( $inSync ) {
				$recordData[Record::CLASSES][] = 'content-in-sync';
			}

			$data[] = new Record( (object)$recordData );
		}

		return $data;
	}

	/**
	 * Gets SHA1-hash of the latest revision content of specified title
	 *
	 * @param Title $title Processing title
	 * @return string SHA1-hash of page's the latest revision content,
	 * 		or empty string if content was not recognized
	 */
	private function getContentHash( Title $title ): string {
		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );

		$updater = $wikiPage->newPageUpdater( User::newSystemUser( 'MediaWiki default' ) );

		$parentRevision = $updater->grabParentRevision();
		if ( $parentRevision === null ) {
			return '';
		}

		$content = $parentRevision->getContent( SlotRecord::MAIN );
		if ( $content instanceof TextContent ) {
			$text = $content->getText();

			return sha1( $text );
		}

		return '';
	}
}
