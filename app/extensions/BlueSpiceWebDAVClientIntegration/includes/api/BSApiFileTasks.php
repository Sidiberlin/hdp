<?php

class BSApiFileTasks extends BSApiTasksBase {
		protected $aTasks = array(
			'getLock'
		);

		protected $aReadTasks = array(
			'getLock',
		);

		protected function getRequiredTaskPermissions() {
			return array(
				"getLock" => array( 'read' )
			);
		}

		public function task_getLock( $oTaskData, $aParams ) {
			$oResponse = $this->makeStandardReturn();

			$sFilename = WebDAVHelper::getFilenameFromUrl( $oTaskData->webdavUrl );
			if ( !$sFilename ) {
				$oResponse->payload = array( 'locked' => false  );
				$oResponse->success = true;
				return $oResponse;
			}
			$dbr = $this->services->getDBLoadBalancer()->getConnection( DB_REPLICA );
			/*
			 * Selecting all locks to avoid complicate
			 * url parsing or converting filename to uri
			 */
			$oLocks = $dbr->select(
				'bs_webdav_locks',
				array(
					'wdl_owner',
					'wdl_uri'
				)
			);
			$iUserId = 0;
			foreach( $oLocks as $oLock ) {
				$sLockFilename = WebDAVHelper::getFilenameFromUrl( $oLock->wdl_uri );
				if( $sLockFilename == $sFilename ) {
					$iUserId = (int) $oLock->wdl_owner;
				}
			}
			if( !$iUserId ) {
				$oResponse->payload = array( 'locked' => false  );
				$oResponse->success = true;
				return $oResponse;
			}

			$oUser = $this->services->getUserFactory()->newFromId( $iUserId );
			$oResponse->payload = array( 'locked' => true,  'user_name' => $oUser->getName()  );
			$oResponse->success = true;
			return $oResponse;
		}
}
