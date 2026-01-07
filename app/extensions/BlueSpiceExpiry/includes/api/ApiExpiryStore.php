<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;

class ApiExpiryStore extends BSApiExtJSStoreBase {

	/**
	 *
	 * @param string $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 */
	public function __construct( $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );
	}

	/**
	 *
	 * @param string $sQuery
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function makeData( $sQuery = '' ) {
		$oUser = RequestContext::getMain()->getUser();
		if ( $oUser->isAnon() ) {
			return [];
		}

		$iOffset = 0;
		$iLimit = 25;
		$sSortField = 'exp_date';
		$sSortDirection = 'DESC';
		$iDate = 0;

		$aData = [
			'results' => [],
			'total' => 0
		];

		$dbr = $this->services->getDBLoadBalancer()->getConnection( DB_REPLICA );

		switch ( $sSortField ) {
			case 'rem_date':
				$sSortField = "bs_expiry.exp_date";
				break;
			case 'page_title':
				$sSortField = "page.page_title";
				break;
		}

		$aTables = [
			'bs_expiry', 'page'
		];
		$aFields = [
			"bs_expiry.exp_id",
			"bs_expiry.exp_page_id",
			"bs_expiry.exp_date",
			"page.page_title",
			"bs_expiry.exp_comment",
		];
		$aConditions = [];
		$aOptions = [
			'ORDER BY' => "{$sSortField} {$sSortDirection}",
			'GROUP BY' => "bs_expiry.exp_id"
		];

		if ( $iOffset === null ) {
			$aOptions['OFFSET'] = $iOffset;
		}

		if ( $iLimit === null ) {
			$aOptions['LIMIT'] = $iLimit;
		}

		$aJoinConditions = [
			"page" => [ 'JOIN', "bs_expiry.exp_page_id = page.page_id" ]
		];

		// give other extensions the opportunity to modify the query
		$this->services->getHookContainer()->run(
			'BsExpiryBeforeBuildOverviewQuery',
			[
				$this,
				&$aTables,
				&$aFields,
				&$aConditions,
				&$aOptions,
				&$aJoinConditions,
				&$sSortField,
				&$sSortDirection
			]
		);

		/*
		$iUserId = $oUser->getId();
		if ( $iUserId && !$oUser->getOption( "MW::Reminder::ShowAllReminders" ) ) {
			$aConditions["bs_reminder.rem_user_id"] = $iUserId;
		}
		*/
		if ( $iDate !== 0 ) {
			$aConditions[] = "bs_expiry.exp_date <= '" . $iDate . "'";
		}

		$res = $dbr->select(
			$aTables, $aFields, $aConditions, __METHOD__, $aOptions, $aJoinConditions
		);

		if ( $res ) {
			$pm = $this->services->getPermissionManager();
			foreach ( $res as $row ) {
				$oTitle = Title::newFromID( $row->exp_page_id );

				if ( !$pm->userCan( 'read', $this->getUser(), $oTitle ) ) {
					continue;
				}

				$date = new DateTime( $row->exp_date );
				$canExpire = $pm->userCan( 'expirearticle', $this->getUser(), $oTitle );
				$canDelete = $pm->userCan( 'expiry-delete', $this->getUser(), $oTitle );
				$aResultSet = [
					'id' => $row->exp_id,
					'page_title' => $oTitle->getFullText(),
					'page_link' => $oTitle->getLocalURL(),
					'expiry_date' => $date->format( 'Y-m-d' ),
					'article_id' => $row->exp_page_id,
					'exp_comment' => $row->exp_comment,
					'user_can_expire' => $canExpire,
					'user_can_delete_expiration' => $canDelete,
				];
				$this->services->getHookContainer()->run( 'BsExpiryBuildOverviewResultSet', [
					$this,
					&$aResultSet,
					$row
				] );
				$aData['results'][] = $aResultSet;
			}
		}

		unset( $aOptions['LIMIT'], $aOptions['OFFSET'] );
		$res = $dbr->select(
			$aTables,
			"COUNT(bs_expiry.exp_id) AS total",
			$aConditions,
			__METHOD__,
			[],
			$aJoinConditions
		);
		if ( $res ) {
			$row = $res->fetchRow();
			$aData['total'] = $row['total'];
		}

		$aReminders = $aData;

		$aOutput = [];

		foreach ( $aReminders['results'] as $aReminder ) {
			$oReminder = (object)$aReminder;
			$aOutput[] = $oReminder;
		}

		return $aOutput;
	}

	/**
	 *
	 * @return bool
	 */
	public function isReadMode() {
		return true;
	}

	/**
	 *
	 * @param string $sQuery
	 * @return array
	 */
	protected function makeMetaData( $sQuery = '' ) {
		$oUser = RequestContext::getMain()->getUser();
		if ( $oUser->isAnon() ) {
			return [];
		}

		$aMetadata = [
			'idProperty' => 'id',
			'root' => 'results',
			'totalProperty' => 'total',
			'successProperty' => 'success',
			'fields' => [
				[ 'name' => 'page_title' ],
				[ 'name' => 'page_link' ],
				[ 'name' => 'exp_date' ],
				[ 'name' => 'article_id' ],
				[ 'name' => 'exp_comment' ]
			],
			'sortInfo' => [
				'field' => 'exp_date',
				'direction' => 'DESC'
			]
		];

		$aMetadata['columns'][] = [
			'header' => wfMessage( 'bs-expiry-header-pagename' )->text(),
			'dataIndex' => 'page_title',
			'render' => 'page',
			'sortable' => true
		];
		$aMetadata['columns'][] = [
			'header' => wfMessage( 'bs-expiry-header-date' )->text(),
			'dataIndex' => 'exp_date',
			'render' => 'date',
			'sortable' => true
		];

		$this->services->getHookContainer()->run( 'BsExpiryBuildOverviewMetadata', [
			&$aMetadata
		] );

		return $aMetadata;
	}

	/**
	 *
	 * @return string
	 */
	protected function getDescription() {
		return 'ExtJS store backend for Expiry';
	}

}
