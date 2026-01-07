<?php

namespace BlueSpice\Bookshelf;

use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Context;
use MWStake\MediaWiki\Component\ManifestRegistry\ManifestAttributeBasedRegistry;
use stdClass;

class ClientConfig {

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
		$modules = array_unique( $modules );
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
				'label' => $context->msg( $tool->getLabelMsgKey() )->text(),
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

		$pageCollectionPrefix = wfMessage( 'bs-pagecollection-prefix' )->inContentLanguage()->text();
		$pageCollectionPrefix = str_replace( ' ', '_', $pageCollectionPrefix );
		$pageCollectionPrefix .= "/";

		$res = $dbr->select(
			'page',
			[ 'page_title' ],
			[
				"page_namespace" => NS_MEDIAWIKI,
				"page_title" . $dbr->buildLike( $pageCollectionPrefix, $dbr->anyString() )
			],
			__METHOD__
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

	/**
	 * @return array
	 */
	public static function getPDFTemplates() {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'PDFCreator' ) ) {
			return [];
		}
		$services = MediaWikiServices::getInstance();
		$configFactory = $services->getConfigFactory();
		$titleFactory = $services->getTitleFactory();
		$pdfcreatorUtil = $services->getService( 'PDFCreator.Util' );
		$templates = $pdfcreatorUtil->getAvailableTemplateNames();
		$bsgConfig = $configFactory->makeConfig( 'bsg' );
		$template = $bsgConfig->get( 'BookshelfDefaultBookTemplate' );
		$templateTitle = $titleFactory->newFromText( 'MediaWiki:PDFCreator/' . $template );
		if ( !$templateTitle->exists() ) {
			return [
				'default' => '',
				'templates' => $templates
			];
		}
		return [
			'default' => $templateTitle->getSubpageText(),
			'templates' => $templates
		];
	}
}
