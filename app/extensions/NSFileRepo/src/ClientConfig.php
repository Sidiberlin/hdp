<?php

namespace MediaWiki\Extension\NSFileRepo;

use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\ResourceLoader\Context as ResourceLoaderContext;

class ClientConfig {

	/**
	 *
	 * @return array
	 */
	public static function makeConfigJson() {
		$config = new Config();
		return [
			'egNSFileRepoSkipTalk' => $config->get( 'SkipTalk' ),
			'egNSFileRepoNamespaceBlacklist' => $config->get( 'NamespaceBlacklist' ),
			'egNSFileRepoNamespaceThreshold' => $config->get( 'NamespaceThreshold' )
		];
	}

	/**
	 * @return array
	 */
	public static function makeNamespaceBuckets( ResourceLoaderContext $context ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$field = '';

		if ( $dbr->getType() === 'mysql' ) {
			$field = 'DISTINCT SUBSTRING_INDEX(img_name, ":", 1) as namespace';
		}
		if ( $dbr->getType() === 'sqlite' ) {
			$field = 'DISTINCT SUBSTR(img_name, 1, INSTR(img_name, ":") - 1) as namespace';
		}
		if ( $dbr->getType() === 'postgres' ) {
			$field = 'DISTINCT SUBSTRING(img_name FROM 1 FOR POSITION(":" IN img_name) - 1) as namespace';
		}

		$res = $dbr->newSelectQueryBuilder()
			->table( 'image' )
			->field( $field )
			->where( [ "img_name LIKE '%:%'" ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$namespaces = [];
		foreach ( $res as $row ) {
			$namespaces[] = $row->namespace;
		}

		// check for any images in the main namespace
		$hasMainNS = $dbr->newSelectQueryBuilder()
			->table( 'image' )
			->field( '1' )
			->where( [ "img_name NOT LIKE '%:%'" ] )
			->limit( 1 )
			->caller( __METHOD__ )
			->fetchField();

		if ( $hasMainNS ) {
			$mainNS = Message::newFromKey( 'nsfilerepo-nsmain' )
				->inLanguage( $context->getLanguage() )
				->useDatabase( false )
				->text();

			$namespaces[] = $mainNS;
		}

		sort( $namespaces );

		return array_map( static function ( $namespace ) {
			return [
				'data' => $namespace,
				'label' => $namespace
			];
		}, $namespaces );
	}
}
