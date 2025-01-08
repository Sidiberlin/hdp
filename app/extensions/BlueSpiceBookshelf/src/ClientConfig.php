<?php

namespace BlueSpice\Bookshelf;

use Config;
use ExtensionRegistry;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Context;
use MWStake\MediaWiki\Component\ManifestRegistry\ManifestAttributeBasedRegistry;
use stdClass;

class ClientConfig {

	/**
	 *
	 * @param Context $context
	 * @param Config $config
	 * @return array
	 */
	public static function makeConfigJson(
		Context $context,
		Config $config
	) {
		$services = MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'bsg' );

		$defaultTemplate = $config->get( 'UEModuleBookPDFDefaultTemplate' );
		$defaultTemplatePath = $config->get( 'UEModuleBookPDFTemplatePath' );

		$availableTemplates = [];
		$dir = opendir( $defaultTemplatePath );
		if ( $dir ) {
			$subDir = readdir( $dir );
			while ( $subDir !== false ) {
				if ( in_array( $subDir, [ '.', '..', 'common' ] ) ) {
					$subDir = readdir( $dir );
					continue;
				}

				if ( !is_dir( "{$defaultTemplatePath}/{$subDir}" ) ) {
					$subDir = readdir( $dir );
					continue;
				}

				if ( file_exists( "{$defaultTemplatePath}/{$subDir}/template.php" ) ) {
					$availableTemplates[] = $subDir;
				}

				$subDir = readdir( $dir );
			}
		}

		if ( empty( $availableTemplates ) ) {
			$defaultTemplate = '';
		} else {
			if ( !in_array( $defaultTemplate, $availableTemplates ) ) {
				$defaultTemplate = $availableTemplates[0];
			}
		}
		return [
			'defaultTemplate' => $defaultTemplate,
			'availableTemplates' => $availableTemplates
		];
	}

	/**
	 *
	 * @return array
	 */
	public static function getRegisteredMetadata() {
		$registry = new ManifestAttributeBasedRegistry(
			'BlueSpiceBookshelfMetaData'
		);
		$services = MediaWikiServices::getInstance();
		$objectFactory = $services->getObjectFactory();

		$pages = [];
		$data = $registry->getAllValues();
		$modules = [];
		foreach ( $data as $key => $spec ) {
			$object = $objectFactory->createObject( $registry->getObjectSpec( $key ) );
			if ( !( $object instanceof IMetaDataDescription ) ) {
				continue;
			}

			$pages[ $key ] = [
				'classname' => $object->getJSClassname(),
				'key' => $object->getKey()
			];
			$modules = array_merge( $modules, $object->getRLModules() );
		}
		array_unique( $modules );
		return [
			'modules' => $modules,
			'pages' => $pages
		];
	}

	/**
	 *
	 * @return array
	 */
	public static function getBookshelfData() {
		$services = MediaWikiServices::getInstance();
		$metaLookup = $services->get( 'BSBookshelfBookMetaLookup' );

		$values = $metaLookup->getAllMetaValuesForKey( 'bookshelf' );
		$values = array_unique( $values );
		return array_values( $values );
	}

	/**
	 *
	 * @return array
	 */
	public static function getCreateNewBookPlugins() {
		$modules = ExtensionRegistry::getInstance()->getAttribute(
			'BlueSpiceBookshelfCreateNewBookModules'
		);

		return $modules;
	}

	/**
	 * @param Context $context
	 * @return array
	 */
	public static function makeBookViewConfig( Context $context ) {
		$tools = [];

		$registry = ExtensionRegistry::getInstance()->getAttribute(
			'BlueSpiceBookshelfBookViewTools'
		);
		$services = MediaWikiServices::getInstance();
		$objectFactory = $services->getObjectFactory();
		$modules = [];
		foreach ( $registry as $key => $spec ) {
			$tool = $objectFactory->createObject( $spec );

			if ( $tool instanceof IBookViewTool === false ) {
				continue;
			}

			if ( !empty( $tool->getRLModules() ) ) {
				$modules = array_merge( $modules, $tool->getRLModules() );
			}

			$tools[] = [
				'type' => $tool->getType(),
				'label' => $context->msg( $tool->getLabelMsgKey() )->plain(),
				'class' => implode( ' ', $tool->getClasses() ),
				'callback' => $tool->getCallback(),
				'slot' => $tool->getSlot(),
				'position' => $tool->getPosition(),
				'modules' => $tool->getRLModules(),
				'permission' => $tool->getRequiredPermission(),
				'selectable' => $tool->requireSelectableTree()
			];
		}

		$config = $services->getConfigFactory()->makeConfig( 'bsg' );
		$offset = $config->get( 'BookshelfToolbarOffset' );

		return [
			'tools' => $tools,
			'modules' => $modules,
			'offset' => $offset
		];
	}

	/**
	 * @return array
	 */
	public static function getPageCollections() {
		$pages = [];
		$services = MediaWikiServices::getInstance();
		$dbr = $services->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$pageCollectionPrefix = wfMessage( 'bs-pagecollection-prefix' )->inContentLanguage()->plain();
		$pageCollectionPrefix = str_replace( ' ', '_', $pageCollectionPrefix );
		$pageCollectionPrefix .= "/";

		$res = $dbr->select(
			'page',
			[ 'page_title' ],
			[
				"page_namespace" => NS_MEDIAWIKI,
				"page_title" . $dbr->buildLike( $pageCollectionPrefix, $dbr->anyString() )
			]
		);

		foreach ( $res as $row ) {
			$pageTitle = str_replace( $pageCollectionPrefix, '', $row->page_title );

			$pageData = new stdClass();
			$pageData->pc_title = $pageTitle;
			$pages[ $pageTitle ] = $pageData;
		}
		ksort( $pages );
		$pages = array_values( $pages );

		return $pages;
	}
}
