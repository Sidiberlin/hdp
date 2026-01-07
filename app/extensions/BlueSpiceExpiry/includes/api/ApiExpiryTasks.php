<?php

use BlueSpice\Api\Response\Standard;
use BlueSpice\Expiry\SpecialLogLogger;
use MediaWiki\Api\ApiMain;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;

class ApiExpiryTasks extends BSApiTasksBase {
	/** @var SpecialLogLogger */
	private $specialLogLogger;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );
		$this->specialLogLogger = $this->services->getService( 'BSExpirySpecialLogLogger' );
	}

	/**
	 *
	 * @var string[]
	 */
	protected $aTasks = [ 'saveExpiry', 'changeDate', 'deleteExpiry', 'getDetailsForExpiry' ];

	/**
	 *
	 * @param \stdClass $oTaskData
	 * @param array $aParams
	 * @return Standard
	 */
	public function task_getDetailsForExpiry( $oTaskData, $aParams ) { // phpcs:ignore MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName, Generic.Files.LineLength.TooLong
		$oResult = $this->makeStandardReturn();

		$iArticleId = isset( $oTaskData->articleId )
			? (int)$oTaskData->articleId
			: 0;

		$aExpiry = \BlueSpice\Expiry\Extension::getExpiryForPage( $iArticleId, false );

		if ( !$aExpiry ) {
			$oResult->message = $oResult->errors[]
				= wfMessage( 'bs-expiry-unknown-page-msg' )->text();
			return $oResult;
		}

		$aReturnData = [];
		$aReturnData['date'] = $aExpiry['exp_date'];
		$aReturnData['id'] = $aExpiry['exp_id'];
		$aReturnData['comment'] = $aExpiry['exp_comment'];

		$oResult->success = true;
		$oResult->payload = $aReturnData;
		return $oResult;
	}

	/**
	 *
	 * @param \stdClass $oTaskData
	 * @param array $aParams
	 * @return Standard
	 */
	public function task_changeDate( $oTaskData, $aParams ) { // phpcs:ignore MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName, Generic.Files.LineLength.TooLong
		$oResult = $this->makeStandardReturn();

		$expiryIds = $oTaskData->ids ?? [];
		$expiryIds = array_map( static function ( $id ) {
			return (int)$id;
		}, $expiryIds );

		$date = DateTime::createFromFormat( "Y-m-d", $oTaskData->date );
		if ( !$date ) {
			$oResult->message = $oResult->errors[]
				= wfMessage( 'bs-expiry-error-invalid-date' )->text();
			return $oResult;
		}

		$dbw = $this->services->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$res = $dbw->update(
			'bs_expiry',
			[ 'exp_date' => $date->format( 'Y-m-d' ) ],
			[ 'exp_id IN (' . $dbw->makeList( $expiryIds ) . ')' ],
			__METHOD__
		);

		$updated = $this->getDB()->select(
			[ 'p' => 'page', 'exp' => 'bs_expiry' ],
			[ 'p.page_title', 'p.page_namespace' ],
			[ 'exp_id IN (' . $dbw->makeList( $expiryIds ) . ')' ],
			__METHOD__,
			[],
			[
				'p' => [
					'INNER JOIN', 'page_id = exp_page_id'
				]
			]
		);
		foreach ( $updated as $row ) {
			$title = $this->services->getTitleFactory()->newFromRow( $row );
			$this->updateCache( $title, $this->getUser() );
			$this->specialLogLogger->log(
				$this->getUser(),
				$title,
				SpecialLogLogger::LOG_ACTION_CHANGE_DATE
			);
		}

		$oResult->success = $res;
		return $oResult;
	}

	/**
	 *
	 * @param \stdClass $oTaskData
	 * @param array $aParams
	 * @return Standard
	 * @throws MWException
	 */
	public function task_saveExpiry( $oTaskData, $aParams ) { // phpcs:ignore MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName, Generic.Files.LineLength.TooLong
		$oResult = $this->makeStandardReturn();
		$oUser = $this->getUser();
		$bIsUpdate = false;
		if ( $oUser->isAnon() ) {
			$oResult->message = $oResult->errors[]
				= wfMessage( 'bs-permissionerror' )->text();
			return $oResult;
		}

		$sComment = strip_tags( empty( $oTaskData->comment ) ? '' : (string)$oTaskData->comment );

		$oTitle = null;
		if ( !empty( $oTaskData->pageName ) ) {
			$oTitle = Title::newFromText( $oTaskData->pageName );
		}
		if ( !empty( $oTaskData->page ) ) {
			$oTitle = Title::newFromText( $oTaskData->page );
		}

		$dbr = $this->services->getDBLoadBalancer()->getConnection( DB_REPLICA );
		// this is normally the case when clicking the expiry on a normal page
		// (not the overview specialpage) or the edit button on the specialpage
		// and data needs to be prefilled
		if ( !$oTitle && empty( $oTaskData->articleId ) && !empty( $oTaskData->id ) ) {
			$res = $dbr->select(
				'bs_expiry',
				'exp_page_id',
				[ 'exp_id' => (int)$oTaskData->id ],
				__METHOD__
			);
			if ( !$res ) {
				$oResult->message = $oResult->errors[] =
					wfMessage( 'bs-expiry-unknown-page-msg' )->text();
			}
			$row = $res->fetchRow();
			if ( empty( $row['exp_page_id'] ) ) {
				$oResult->message = $oResult->errors[] =
					wfMessage( 'bs-expiry-unknown-page-msg' )->text();
				return $oResult;
			}
			$oTaskData->articleId = (int)$row['exp_page_id'];
		}

		if ( !$oTitle && !empty( $oTaskData->articleId ) ) {
			$oTitle = Title::newFromID( $oTaskData->articleId );
		}

		if ( !$oTitle instanceof Title || !$oTitle->exists() ) {
			$oResult->message = $oResult->errors[] =
				wfMessage( 'bs-expiry-unknown-page-msg' )->text();
			return $oResult;
		}

		$dateRaw = $oTaskData->date;
		if ( is_int( $dateRaw ) ) {
			$dateRaw = date( 'Y-m-d', $dateRaw );
		}
		$date = DateTime::createFromFormat( "Y-m-d", $dateRaw );
		if ( !$date ) {
			$oResult->message = $oResult->errors[]
				= wfMessage( 'bs-expiry-error-invalid-date' )->text();
			return $oResult;
		}

		$iExpiryId = property_exists( $oTaskData, 'id' ) ? (int)$oTaskData->id : 0;
		if ( !$iExpiryId ) {
			$res = $dbr->select(
				'bs_expiry',
				'*',
				[
					'exp_page_id' => (int)$oTitle->getArticleID()
				],
				__METHOD__
			);
			if ( $res && $res->numRows() ) {
				$row = $res->fetchRow();
				$iExpiryId = (int)$row['exp_id'];
			}
		}
		if ( $iExpiryId > 0 ) {
			$bIsUpdate = true;
		}

		$aData = [
			'exp_page_id' => (int)$oTitle->getArticleID(),
			'exp_date' => $date->format( 'Y-m-d' ),
			'exp_comment' => $sComment
		];

		$dbw = $this->services->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		if ( !$iExpiryId ) {
			$res = $dbw->insert( 'bs_expiry', $aData, __METHOD__ );
			if ( !$res ) {
				$oResult->message = $oResult->errors[] =
					wfMessage( 'bs-expiry-create-error' )->text();
				return $oResult;
			}

			$iExpiryId = $dbw->insertId();

			try {
				$this->services->getHookContainer()->run( 'BsExpiryOnSave', [
					$oTaskData,
					$iExpiryId,
					// deprecated
					$oTitle->getArticleID(),
					// deprecated
					$oUser->getId(),
					$oUser,
					$oTitle,
				] );
			} catch ( Exception $e ) {
				$oResult->message = $oResult->errors[] =
					$e->getMessage();
				return $oResult;
			}
		} else {
			$res = $dbw->update(
				'bs_expiry',
				$aData,
				[ 'exp_id' => $iExpiryId ],
				__METHOD__
			);
			if ( !$res ) {
				$oResult->message = $oResult->errors[] =
					wfMessage( 'bs-expiry-update-error' )->text();
				return $oResult;
			}

			try {
				$this->services->getHookContainer()->run( 'BsExpiryOnUpdate', [
					$oTaskData,
					$iExpiryId,
					$oUser,
					$oTitle,
				] );
			} catch ( Exception $e ) {
				$oResult->message = $oResult->errors[] =
					$e->getMessage();
				return $oResult;
			}
		}

		$oResult->success = true;

		$this->updateCache( $oTitle, $this->getUser() );

		if ( $bIsUpdate ) {
			$this->specialLogLogger->log(
				$this->getUser(),
				$oTitle,
				SpecialLogLogger::LOG_ACTION_CHANGE_DATE
			);
			$oResult->message = wfMessage( "bs-expiry-update-success" )->text();
		} else {
			$oResult->message = wfMessage( "bs-expiry-save-success" )->text();
		}

		return $oResult;
	}

	/**
	 *
	 * @param \stdClass $oTaskData
	 * @param array $aParams
	 * @return Standard
	 */
	public function task_deleteExpiry( $oTaskData, $aParams ) { // phpcs:ignore MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName, Generic.Files.LineLength.TooLong
		$oResult = $this->makeStandardReturn();

		// Check if there is a expiryId
		$iExpiryId = (int)$oTaskData->expiryId;
		$iArticleId = (int)$oTaskData->articleId;
		if ( !$iExpiryId ) {
			$oResult->message = $oResult->errors[] =
				wfMessage( 'bs-expiry-unexpire-unsuccess' )->text();
			return $oResult;
		}
		if ( !$iArticleId ) {
			$iArticleId = $this->retrieveArticleIdForExpiry( $iExpiryId );
		}

		// All ok, do the actual deletion
		$dbw = $this->services->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->delete(
			'bs_expiry',
			[
				'exp_id' => $iExpiryId
			],
			__METHOD__
		);

		if ( $iArticleId ) {
			$oTitle = Title::newFromID( $iArticleId );
			if ( $oTitle ) {
				$this->updateCache( $oTitle, $this->getUser() );
				$this->specialLogLogger->log(
					$this->getUser(),
					$oTitle,
					SpecialLogLogger::LOC_ACTION_DELETE
				);
			}
		}

		$oResult->success = true;
		$oResult->message = wfMessage( 'bs-expiry-unexpire-success' )->text();
		return $oResult;
	}

	/**
	 *
	 * @return array
	 */
	protected function getRequiredTaskPermissions() {
		return [
			'getDetailsForExpiry' => [ 'read' ],
			'saveExpiry' => [ 'expirearticle' ],
			'changeDate' => [ 'expirearticle' ],
			'deleteExpiry' => [ 'expiry-delete' ]
		];
	}

	/**
	 * @param int $iExpiryId
	 * @return int|null
	 */
	private function retrieveArticleIdForExpiry( int $iExpiryId ) {
		$res = $this->getDB()->selectRow(
			'bs_expiry',
			[ 'exp_page_id' ],
			[ 'exp_id' => $iExpiryId ],
			__METHOD__
		);

		if ( !$res ) {
			return null;
		}
		return (int)$res->exp_page_id;
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @return void
	 */
	private function updateCache( Title $title, UserIdentity $user ) {
		$wikiPage = $this->services->getWikiPageFactory()->newFromTitle( $title );
		$wikiPage->doSecondaryDataUpdates( [
			'triggeringUser' => $user,
			'defer' => DeferredUpdates::POSTSEND
		] );
		$title->invalidateCache();
	}
}
