<?php

if ( defined( 'MWSTAKE_MEDIAWIKI_COMPONENT_COMMONWEBAPIS_VERSION' ) ) {
	return;
}

define( 'MWSTAKE_MEDIAWIKI_COMPONENT_COMMONWEBAPIS_VERSION', '2.0.28' );

MWStake\MediaWiki\ComponentLoader\Bootstrapper::getInstance()
	->register( 'commonwebapis', static function () {
		$GLOBALS['wgExtensionFunctions'][]
			= "\\MWStake\\MediaWiki\\Component\\CommonWebAPIs\\Setup::onExtensionFunctions";
		$GLOBALS['wgServiceWiringFiles'][] = __DIR__ . '/includes/ServiceWiring.php';

		$GLOBALS['wgExtensionFunctions'][] = static function () {
			$lb = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancer();
			$pageProps = \MediaWiki\MediaWikiServices::getInstance()->getPageProps();
			$titleIndexUpdater = new \MWStake\MediaWiki\Component\CommonWebAPIs\TitleIndexUpdater( $lb, $pageProps );
			$userIndexUpdater = new \MWStake\MediaWiki\Component\CommonWebAPIs\UserIndexUpdater( $lb );
			$categoryIndexUpdater = new \MWStake\MediaWiki\Component\CommonWebAPIs\CategoryIndexUpdater( $lb );
			$hookContainer = \MediaWiki\MediaWikiServices::getInstance()->getHookContainer();

			$hookContainer->register( 'LoadExtensionSchemaUpdates', static function ( $updater ) {
				if ( !$updater instanceof DatabaseUpdater ) {
					throw new \MWException( "LoadExtensionSchemaUpdates hook must be called with a DatabaseUpdater" );
				}
				$updater->addExtensionTable(
					'mws_user_index',
					__DIR__ . "/sql/mws_user_index.sql"
				);
				$updater->addExtensionTable(
					'mws_title_index',
					__DIR__ . "/sql/mws_title_index.sql"
				);
				$updater->addExtensionField(
					'mws_title_index',
					'mti_displaytitle',
					__DIR__ . "/sql/mws_title_index_displaytitle_patch.sql"
				);
				$updater->addExtensionTable(
					'mws_category_index',
					__DIR__ . "/sql/mws_category_index.sql"
				);

				$updater->addExtensionField(
					'mws_category_index',
					'mci_page_title',
					__DIR__ . "/sql/mws_category_index_patch_page_title.sql"
				);

				$updater->addPostDatabaseUpdateMaintenance(
					\MWStake\MediaWiki\Component\CommonWebAPIs\Maintenance\PopulateUserIndex::class
				);
				$updater->addPostDatabaseUpdateMaintenance(
					\MWStake\MediaWiki\Component\CommonWebAPIs\Maintenance\PopulateTitleIndex::class
				);
				$updater->addPostDatabaseUpdateMaintenance(
					\MWStake\MediaWiki\Component\CommonWebAPIs\Maintenance\PopulateCategoryIndex::class
				);
			} );

			$hookContainer->register( 'UserSaveSettings', [ $userIndexUpdater, 'store' ] );

			$hookContainer->register( 'PageSaveComplete', [ $titleIndexUpdater, 'onPageSaveComplete' ] );
			$hookContainer->register( 'PageSaveComplete', [ $categoryIndexUpdater, 'onPageSaveComplete' ] );
			$hookContainer->register( 'PageMoveComplete', [ $titleIndexUpdater, 'onPageMoveComplete' ] );
			$hookContainer->register( 'PageMoveComplete', [ $categoryIndexUpdater, 'onPageMoveComplete' ] );
			$hookContainer->register( 'PageDeleteComplete', [ $titleIndexUpdater, 'onPageDeleteComplete' ] );
			$hookContainer->register( 'PageDeleteComplete', [ $categoryIndexUpdater, 'onPageDeleteComplete' ] );
			$hookContainer->register( 'ArticleUndelete', [ $titleIndexUpdater, 'onArticleUndelete' ] );
			$hookContainer->register( 'ArticleUndelete', [ $categoryIndexUpdater, 'onArticleUndelete' ] );
			$hookContainer->register( 'AfterImportPage', [ $titleIndexUpdater, 'onAfterImportPage' ] );
			$hookContainer->register( 'AfterImportPage', [ $categoryIndexUpdater, 'onAfterImportPage' ] );
			$hookContainer->register( 'CategoryAfterPageAdded', [ $categoryIndexUpdater, 'onCategoryAfterPageAdded' ] );
			$hookContainer->register(
				'CategoryAfterPageRemoved', [ $categoryIndexUpdater, 'onCategoryAfterPageRemoved' ]
			);
		};

		$GLOBALS['wgResourceModules']['ext.mws.commonwebapis'] = [
			'scripts' => [
				'api.js'
			],
			'localBasePath' => __DIR__ . '/resources'
		];

		// Exclude users from these groups in user store
		$GLOBALS['mwsgCommonWebAPIsComponentUserStoreExcludeGroups'] = [ 'bot' ];
		// Exclude porticular users from user store
		$GLOBALS['mwsgCommonWebAPIsComponentUserStoreExcludeUsers'] = array_merge(
			$GLOBALS['wgReservedUsernames'] ?? [], [ 'Mediawiki default' ]
		);
		// Exclude these groups from group store
		$GLOBALS['mwsgCommonWebAPIsComponentGroupStoreExcludeGroups'] = [ 'bot' ];
	} );
