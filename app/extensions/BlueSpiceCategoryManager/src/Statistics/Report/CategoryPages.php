<?php

namespace BlueSpice\CategoryManager\Statistics\Report;

use BlueSpice\ExtendedStatistics\ClientReportHandler;
use BlueSpice\ExtendedStatistics\IReport;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\TitleFactory;

class CategoryPages implements IReport {

	/** @var TitleFactory */
	private TitleFactory $titleFactory;

	/**
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( TitleFactory $titleFactory ) {
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function getSnapshotKey() {
		return 'cm-categories';
	}

	/**
	 * @inheritDoc
	 */
	public function getClientData( $snapshots, array $filterData, $limit = 20 ): array {
		$namespace = $this->getNamespace( $filterData );
		if ( empty( $filterData['categories'] ) ) {
			return [];
		}

		$categories = [];
		foreach ( $filterData['categories'] as $category ) {
			$title = $this->titleFactory->newFromDBkey( $category );
			if ( $title ) {
				$categories[] = $title->getDBkey();
			}
		}

		$processed = [];
		foreach ( $snapshots as $snapshot ) {
			if ( $namespace ) {
				$data = $snapshot->getData()['namespaces'];
				if ( !isset( $data[$namespace] ) ) {
					return [];
				}
				$data = $data[$namespace];
			} else {
				$data = $snapshot->getData()['all'];
			}
			foreach ( $data as $cat => $props ) {
				if ( !in_array( $cat, $categories ) ) {
					continue;
				}
				$processed[] = [
					'name' => $snapshot->getDate()->forGraph(),
					'line' => $cat,
					'value' => $props['pages']
				];
			}
		}

		return $processed;
	}

	/**
	 * @inheritDoc
	 */
	public function getClientReportHandler(): ClientReportHandler {
		return new ClientReportHandler(
			[ 'ext.bluespice.categorymanager.statistics' ],
			'bs.categoryManager.report.CategoryPagesReport'
		);
	}

	/**
	 * @param array $filterData
	 * @return string|null
	 */
	private function getNamespace( array $filterData ) {
		$namespace = $filterData['namespace'] ?? null;
		if ( $namespace === null ) {
			return null;
		}
		if ( $namespace === '' ) {
			return null;
		}

		if ( (int)$namespace === 0 ) {
			return '-';
		}

		return MediaWikiServices::getInstance()
			->getNamespaceInfo()
			->getCanonicalName( $namespace );
	}
}
