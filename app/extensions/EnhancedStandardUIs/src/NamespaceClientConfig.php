<?php

namespace MediaWiki\Extension\EnhancedStandardUIs;

use Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Context;

class NamespaceClientConfig {

	/**
	 *
	 * @param Context $context
	 * @param Config $config
	 * @param array $param
	 * @return array
	 */
	public static function makeNamespaceConfigJson( Context $context, Config $config, $param ) {
		$services = MediaWikiServices::getInstance();

		$pageCounts = static::getNamespacePageCount( $services->getDBLoadBalancer() );

		$langCode = $context->getLanguage();
		$languageFactory = $services->getLanguageFactory();
		$lang = $languageFactory->getLanguage( $langCode );

		$namespaceInfo = $services->getNamespaceInfo();
		$namespaces = [];
		foreach ( $lang->getFormattedNamespaces() as $ns => $title ) {
			$namespaces[] = [
				'id' => $ns,
				'name' => $title,
				'isContent' => $namespaceInfo->isContent( $ns ),
				'isTalk' => $namespaceInfo->isTalk( $ns ),
				'pageCount' => (int)( $pageCounts[$ns] ?? 0 )
			];
		}
		usort( $namespaces, static function ( $a, $b ) {
			return $a[ 'name' ] <=> $b[ 'name' ];
		} );

		return $namespaces;
	}

	/**
	 * Undocumented function
	 *
	 * @param ILoadBalancer $dbLoadBalancer
	 * @return array
	 */
	private static function getNamespacePageCount( $dbLoadBalancer ) {
		$pageCounts = [];
		$dbr = $dbLoadBalancer->getConnectionRef( DB_REPLICA );
		$res = $dbr->select(
			'page',
			[
				'page_namespace',
				'COUNT(*) AS count'
			],
			[
				'page_is_redirect' => 0
			],
			__METHOD__,
			[
				'GROUP BY' => 'page_namespace'
			]
		);
		foreach ( $res as $row ) {
			$pageCounts[(int)$row->page_namespace] = $row->count;
		}
		return $pageCounts;
	}
}
