<?php

use BlueSpice\Api\Response\Standard;

class ApiBookshelfManage extends BSApiTasksBase {

	/**
	 *
	 * @var string[]
	 */
	protected $aTasks = [
		'deleteBook'
	];

	/**
	 *
	 * @return array
	 */
	protected function getRequiredTaskPermissions() {
		return [
			'deleteBook' => [ 'edit' ]
		];
	}

	/**
	 *
	 * @return string
	 */
	public function getDescription() {
		return 'Allows management of books inside the wiki';
	}

	/**
	 *
	 * @param \stdClass $aTaskData
	 * @param array $aParams
	 * @return Standard
	 */
	public function task_deleteBook( $aTaskData, $aParams ) {
		$oResult = $this->makeStandardReturn();

		$oTitle = Title::newFromId( $aTaskData->book_page_id );
		if ( !( $oTitle instanceof Title ) ) {
			$oResult->message = $oResult->errors['pageid'] =
				wfMessage( 'bs-bookshelfui-bookmanager-deletion-error-pageid' )->text();
			return $oResult;
		}

		if ( !$this->services->getPermissionManager()
			->userCan( 'delete', $this->getUser(), $oTitle )
		) {
			$oResult->message = $oResult->errors['permission'] =
				wfMessage( 'bs-bookshelfui-bookmanager-deletion-error-permission' )->text();
			return $oResult;
		}

		$page = $this->services->getWikiPageFactory()->newFromTitle( $oTitle );
		$deletePage = $this->services->getDeletePageFactory()->newDeletePage( $page, $this->getUser() );
		$deleteStatus = $deletePage->deleteIfAllowed(
			wfMessage( 'bs-bookshelfui-bookmanager-deletion-reason' )->text()
		);

		if ( $deleteStatus->isGood() == false ) {
			$oResult->message =
				wfMessage( 'bs-bookshelfui-bookmanager-deletion-error-unkown' )->text();
			// 'getLegacyHookErrors()' is '@internal' - used for backwards compatibility
			$oResult->errors['saving'] = $deletePage->getLegacyHookErrors();
			$dbw = $this->services->getDBLoadBalancer()->getConnection( DB_PRIMARY );
			wfDebugLog(
				'BS::Bookshelf',
				'SpecialBookshelfBookManager::ajaxDeleteBook: ' . $dbw->lastQuery()
			);
		} else {
			$oResult->success = true;
		}
		return $oResult;
	}
}
