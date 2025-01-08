<?php

namespace BlueSpice\CategoryManager\Statistics\SnapshotProvider;

use BlueSpice\ExtendedStatistics\ISnapshotProvider;
use BlueSpice\ExtendedStatistics\Snapshot;
use BlueSpice\ExtendedStatistics\SnapshotDate;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\LoadBalancer;

class Categories implements ISnapshotProvider {
	/** @var LoadBalancer */
	private $loadBalancer;

	/**
	 * @param LoadBalancer $loadBalancer
	 */
	public function __construct( LoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @param SnapshotDate $date
	 * @return Snapshot
	 */
	public function generateSnapshot( SnapshotDate $date ): Snapshot {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );

		$res = $db->select(
			'category',
			[ 'cat_title', 'cat_pages', 'cat_subcats', 'cat_files' ],
			[],
			__METHOD__
		);

		$main = [];
		foreach ( $res as $row ) {
			$cat = $row->cat_title;
			$pages = (int)$row->cat_pages;
			$subcats = (int)$row->cat_subcats;
			$files = (int)$row->cat_files;
			$main[$cat] = [
				'pages' => $pages,
				'subcats' => $subcats,
				'files' => $files,
			];
		}

		$res = $db->select(
			[ 'c' => 'category', 'cl' => 'categorylinks', 'p' => 'page' ],
			[ 'cat_title', 'COUNT(cl_from) as pages', 'page_namespace' ],
			[],
			__METHOD__,
			[
				'GROUP BY' => [ 'cl_from, cat_title' ],
			],
			[
				'cl' => [
					"INNER JOIN", [ 'cl_to=cat_title' ]
				],
				'p' => [
					"INNER JOIN", [ 'page_id=cl_from' ]
				]
			]
		);

		$namespaces = [];
		$namespaceInfo = MediaWikiServices::getInstance()->getNamespaceInfo();
		foreach ( $res as $row ) {
			if ( (int)$row->page_namespace === 0 ) {
				$ns = '-';
			} else {
				$ns = $namespaceInfo->getCanonicalName( $row->page_namespace );
			}
			$cat = $row->cat_title;

			if ( !isset( $namespaces[$ns] ) ) {
				$namespaces[$ns] = [];
			}
			if ( !isset( $namespaces[$ns][$cat] ) ) {
				$namespaces[$ns][$cat] = [
					'pages' => 0,
					'subcats' => $main[$cat]['subcats'] ?? 0,
					'files' => $main[$cat]['files'] ?? 0,
				];
			}

			$namespaces[$ns][$cat]['pages'] += (int)$row->pages;
		}

		return new Snapshot( $date, $this->getType(), [
			'all' => $main,
			'namespaces' => $namespaces,
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function aggregate(
		array $snapshots, $interval = Snapshot::INTERVAL_DAY, $date = null
	): Snapshot {
		$lastSnapshot = array_pop( $snapshots );

		return new Snapshot(
			$date ?? new SnapshotDate(), $this->getType(), $lastSnapshot->getData(), $interval
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getType() {
		return 'cm-categories';
	}

	/**
	 * @inheritDoc
	 */
	public function getSecondaryData( Snapshot $snapshot ) {
		return null;
	}
}
