<?php

use MediaWiki\Api\ApiBase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * This class serves as a backend for the wiki explorer store.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * This file is part of BlueSpice MediaWiki
 * For further information visit https://bluespice.com
 *
 * @author     Patric Wirth
 * @package    BluespiceWikiExplorer
 * @copyright  Copyright (C) 2016 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GPL-3.0-only
 *
 * Example request parameters of an ExtJS store
 */
class BSApiWikiExplorerStore extends BSApiWikiPageStore {

	/**
	 * Page ID to its first revision timestamp mapping.
	 *
	 * @var array
	 */
	private $pageFirstRevisionTimestamp = [];

	/**
	 * Gets timestamp of first revision for each wiki page existing.
	 *
	 * @return array Page ID to its first revision timestamp mapping
	 * @see \BSApiWikiExplorerStore::makeData()
	 */
	private function getPagesFirstRevisionTimestamps() {
		$pagesFirstRevisions = $this->getDB()->select(
			[ 'page', 'revision' ],
			[ 'page_id', 'rev_timestamp' ],
			[ 'rev_parent_id' => 0 ],
			__METHOD__,
			[],
			[
				'revision' => [
					'INNER JOIN',
					'page_id=rev_page'
				]
			]
		);

		$pageFirstRevisionTimestamp = [];
		foreach ( $pagesFirstRevisions as $row ) {
			$pageFirstRevisionTimestamp[$row->page_id] = $row->rev_timestamp;
		}

		return $pageFirstRevisionTimestamp;
	}

	/**
	 * @param Instance $oInstance
	 * @param Query $sQuery
	 * @param array $aFilter
	 * @param array &$aTables
	 * @param array &$aFields
	 * @param array &$aConditions
	 * @param array &$aOptions
	 * @param array &$aJoinOptions
	 * @param array &$aData
	 * @return bool
	 */
	public static function onBeforeQuery( $oInstance, $sQuery, $aFilter, &$aTables, &$aFields,
		&$aConditions, &$aOptions, &$aJoinOptions, &$aData ) {
		MediaWikiServices::getInstance()->getHookContainer()->run(
			'WikiExplorer::queryPagesWithFilter',
			[
				$aFilter,
				&$aTables,
				&$aFields,
				&$aConditions,
				&$aJoinOptions
			]
		);

		$aTables = array_unique( $aTables );
		$aFields = array_unique( $aFields );
		$aConditions = array_unique( $aConditions );
		$aOptions = array_unique( $aOptions );
		$aJoinOptions = array_unique( $aJoinOptions, SORT_REGULAR );

		return true;
	}

	/**
	 * @param array &$aColumns
	 */
	public static function onGetColumnDefinitions( &$aColumns ) {
		$aRenderer = [
			'page_assignments' => 'WikiExplorerLecayRender_page_assignments',
			'contentstabilization_state' => 'WikiExplorerLecayRender_contentstabilization_state',
			'contentstabilization_date' => 'WikiExplorerLecayRender_contentstabilization_date',
			'contentstabilization_is_new_available' => 'WikiExplorerLecayRender_contentstabilization_is_new_available',
		];
		foreach ( $aColumns as $iKey => $aColumn ) {
			if ( !isset( $aRenderer[$aColumn['dataIndex']] ) ) {
				continue;
			}
			$aColumns[$iKey]['render'] = $aRenderer[$aColumn['dataIndex']];
			if ( $aColumn['dataIndex'] == 'contentstabilization_date' ) {
				$aColumns[$iKey]['filter'] = [
					'type' => 'date',
					'dataFormat' => 'Ymd',
				];
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function checkDatasetPermission( Title $title ) {
		// For optimization reasons:

		// Even in case if user will see some pages he does not have access to - user's
		// permissions will anyway be checked when redirecting to particular page.
		// And if user does not have read permission - it will be denied.

		// So we do not need to check read permissions in WikiExplorer.
		// Thus it's expensive operation and will be done for each of thousands pages - it can be omitted.

		return true;
	}

	/**
	 * @param stdClass $row
	 * @return stdClass|bool
	 */
	public function makeDataSet( $row ) {
		set_time_limit( 120 );
		$row = parent::makeDataSet( $row );
		if ( !$row ) {
			return $row;
		}

		$row->page_created = $this->pageFirstRevisionTimestamp[$row->page_id];
		$row->page_categories = [];
		$row->page_links = [];
		$row->page_linked_files = [];

		return $row;
	}

	/**
	 * Extends rows with metadata: Categories, page links and image links
	 * @param array $aData
	 * @return array
	 */
	public function extendRows( $aData ) {
		$aPageIds = [];
		foreach ( $aData as $oRow ) {
			$aPageIds[$oRow->page_id] = $oRow;
		}

		// Categories
		$oCatRes = $this->getDB( DB_REPLICA )->select(
			'categorylinks',
			[ 'cl_from', 'cl_to' ],
			[ 'cl_from' => array_keys( $aPageIds ) ]
		);
		foreach ( $oCatRes as $oCatRow ) {
			$aPageIds[$oCatRow->cl_from]->page_categories[] = $oCatRow->cl_to;
		}

		// Page links
		$oPageRes = $this->getDB( DB_REPLICA )->select(
			[ 'pagelinks', 'linktarget' ],
			[ 'pl_from', 'lt_title', 'lt_namespace' ],
			[ 'pl_from' => array_keys( $aPageIds ) ],
			__METHOD__,
			[],
			[
				'pagelinks' => [ 'INNER JOIN', 'pl_target_id=lt_id' ],
			]
		);
		foreach ( $oPageRes as $oPageRow ) {
			$sNS = '';
			if ( !empty( $oPageRow->pl_namespace ) ) {
				$sNS = BsNamespaceHelper::getNamespaceName(
					$oPageRow->pl_namespace
				);
				$sNS .= ':';
			}
			$aPageIds[$oPageRow->pl_from]->page_links[]
				= "$sNS$oPageRow->pl_title";
		}

		// Image links
		$oImageRes = $this->getDB( DB_REPLICA )->select(
			'imagelinks',
			[ 'il_from', 'il_to' ],
			[ 'il_from' => array_keys( $aPageIds ) ],
			__METHOD__
		);
		foreach ( $oImageRes as $oImageRow ) {
			$aPageIds[$oImageRow->il_from]->page_linked_files[] = $oImageRow->il_to;
		}

		// return only the values to reset the array counter or get notices
		return array_values( $aPageIds );
	}

	/**
	 * @param string $sQuery
	 * @param array $aFilter
	 * @return array
	 */
	public function makeTables( $sQuery, $aFilter ) {
		$queryBuilder = $this->services->getRevisionStore()
			->newSelectQueryBuilder( $this->getDb() )
			->joinComment()
			->table( 'page' );

		$queryInfo = $queryBuilder->getQueryInfo();

		return $queryInfo['tables'];
	}

	/**
	 * @param string $sQuery
	 * @param array $aFilter
	 * @return array
	 */
	public function makeFields( $sQuery, $aFilter ) {
		$queryBuilder = $this->services->getRevisionStore()
			->newSelectQueryBuilder( $this->getDb() )
			->joinComment()
			->fields( [
				'page_is_redirect',
				'page_is_new',
				'page_touched',
				'page_len',
				'page_latest',
			] );

		$queryInfo = $queryBuilder->getQueryInfo();

		return array_merge(
			parent::makeFields( $sQuery, $aFilter ),
			$queryInfo['fields']
		);
	}

	/**
	 * @param string $sQuery
	 * @param array $aFilter
	 * @return array
	 */
	public function makeConditions( $sQuery, $aFilter ) {
		return [ 'page_content_model' => [ '', 'wikitext' ] ];
	}

	/**
	 * @param string $sQuery
	 * @param array $aFilter
	 * @return array
	 */
	public function makeOptions( $sQuery, $aFilter ) {
		return [
			'GROUP BY' => 'page_id',
		];
	}

	/**
	 * @param string $sQuery
	 * @param array $aFilter
	 * @return array
	 */
	public function makeJoinOptions( $sQuery, $aFilter ) {
		$queryBuilder = $this->services->getRevisionStore()
			->newSelectQueryBuilder( $this->getDb() )
			->joinComment()
			->leftJoin(
				'revision',
				null,
				'page_latest = rev_id'
			);

		$queryInfo = $queryBuilder->getQueryInfo();

		return $queryInfo['join_conds'];
	}

	/**
	 * Filter, sort and trim the result according to the call parameters and
	 * apply security trimming
	 * @param array $aData
	 * @return array
	 */
	public function postProcessData( $aData ) {
		$res = $this->services->getHookContainer()->run(
			'BSApiExtJSStoreBaseBeforePostProcessData',
			[
				$this,
				&$aData
			]
		);
		if ( !$res ) {
			return $aData;
		}

		$aProcessedData = [];

		// First, apply filter
		$aProcessedData = array_filter(
			$aData,
			[ $this, 'filterCallback' ]
		);
		$this->services->getHookContainer()->run( 'BSApiExtJSStoreBaseAfterFilterData', [
			$this,
			&$aProcessedData
		] );

		// Next, apply sort
		$aProcessedData = $this->sortData( $aProcessedData );

		// Before we trim, we save the count
		$this->iFinalDataSetCount = count( $aProcessedData );

		// Last, do trimming
		$aProcessedData = $this->trimData( $aProcessedData );

		// Add page_link field
		$aProcessedData = $this->addSecondaryFields( $aProcessedData );

		return $aProcessedData;
	}

	/**
	 * Override corresponding method from parent class to add page_link field
	 * @param array $aTrimmedData
	 * @return array
	 */
	protected function addSecondaryFields( $aTrimmedData ) {
		foreach ( $aTrimmedData as &$oDataSet ) {
			$oTitle = $this->services->getTitleFactory()->makeTitle(
				$oDataSet->page_namespace,
				$oDataSet->page_title
			);

			$oDataSet->page_link = $oTitle->getLinkURL();
		}

		return $aTrimmedData;
	}

	/**
	 * @return array
	 */
	public function getAllowedParams() {
		return parent::getAllowedParams() + [
			'metaLoaded' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ApiBase::PARAM_HELP_MSG => 'apihelp-bs-wikiexplorer-store-param-metaLoaded',
			],
		];
	}

	/**
	 * C&p of WikiExplorer::getData()
	 * @param string $sQuery
	 * @return array
	 */
	public function makeData( $sQuery = '' ) {
		// DEPRECATED! Legacy query building - Please do not use!
		$this->services->getHookContainer()->register(
			'BSApiExtJSDBTableStoreBeforeQuery',
			[ 'BSApiWikiExplorerStore::onBeforeQuery' ]
		);
		$this->services->getHookContainer()->register(
			'WikiExplorer::getColumnDefinitions',
			[ 'BSApiWikiExplorerStore::onGetColumnDefinitions' ]
		);

		set_time_limit( 120 );

		$this->pageFirstRevisionTimestamp = $this->getPagesFirstRevisionTimestamps();

		$aData = parent::makeData( $sQuery );

		if ( empty( $aData ) ) {
			return $aData;
		}

		// DEPRECATED! Legacy data set building - Please do not use! Get rid of
		// this as soon as possible!
		$aDeprecatedData = [];
		foreach ( $aData as $iKey => $oRow ) {
			$aDeprecatedData[$oRow->page_id] = (array)$oRow;
		}
		$this->services->getHookContainer()->run( 'WikiExplorer::buildDataSets', [
			&$aDeprecatedData
		] );
		foreach ( $aDeprecatedData as $iKey => $aRow ) {
			$aDeprecatedData[$iKey] = (object)$aRow;
		}

		return $this->extendRows( $aDeprecatedData );
	}

	/**
	 * @param \stdClass $aDataSet
	 * @return bool
	 */
	public function filterCallback( $aDataSet ) {
		set_time_limit( 120 );
		$aFilter = $this->getParameter( 'filter' );
		foreach ( $aFilter as $oFilter ) {
			// Check possible custom filters
			$bResult = true;
			$bReturn = $this->services->getHookContainer()->run(
				'WikiExplorerStoreFilterCallbck',
				[
					$aDataSet,
					$oFilter,
					&$bResult,
				]
			);

			// Force return true when hook handler gets abborted.
			// Also return false result immediately
			if ( !$bReturn || !$bResult ) {
				return $bResult;
			}
		}

		return parent::filterCallback( $aDataSet );
	}

	/**
	 * @inheritDoc
	 */
	public function filterString( $oFilter, $aDataSet ) {
		$aArrayFilters = [
			'page_categories',
			'page_links',
			'page_linked_files',
		];
		if ( in_array( $oFilter->field, $aArrayFilters ) ) {
			$matches = false;
			foreach ( $aDataSet->{$oFilter->field} as $sValue ) {
				$bRes = parent::filterString(
					$oFilter,
					(object)[ $oFilter->field => $sValue ]
				);
				if ( !$bRes ) {
					continue;
				}
				$matches = true;
			}

			return $matches;
		}

		// Check namespace names instead of index numbers
		if ( $oFilter->field === 'page_namespace' ) {
			$sNs = BsNamespaceHelper::getNamespaceName(
				$aDataSet->{$oFilter->field}
			);
			$bRes = parent::filterString(
				$oFilter,
				(object)[ $oFilter->field => $sNs ]
			);
			if ( !$bRes ) {
				return false;
			}

			return true;
		}

		return parent::filterString( $oFilter, $aDataSet );
	}
}
