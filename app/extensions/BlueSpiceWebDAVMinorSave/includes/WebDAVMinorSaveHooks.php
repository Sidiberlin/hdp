<?php

use MediaWiki\MediaWikiServices;

class WebDAVMinorSaveHooks {
	/**
	 * Adds the saves table to the database
	 * @param DatabaseUpdater $updater
	 * @return boolean
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dbType = $updater->getDB()->getType();
		$dir = dirname( __DIR__ );

		$updater->addExtensionTable(
			'bs_webdav_saves',
			"$dir/db/$dbType/webdav.saves.sql"
		);
		return true;
	}

	/**
	 * Saves info on temp location of the file,
	 * while its being edited
	 *
	 * @param string $sTmpFile
	 * @param File $oFile
	 *
	 * @return bool false to stop execution of the hook
	 */
	public static function onWebDAVFileFilePutBeforePublish( $sTmpFile, $oFile ) {
		$oUser = RequestContext::getMain()->getUser();
		$sName = self::normalizeName( $oFile->getName() );
		$oDB = wfGetDB( DB_PRIMARY );
		$oRes = $oDB->delete(
			'bs_webdav_saves',
			array(
				'wds_user_id' => $oUser->getId(),
				'wds_filename' => $sName
			)
		);
		if( $oRes === false ) {
			return true;
		}
		$oRes = $oDB->insert(
			'bs_webdav_saves',
			array(
				'wds_user_id' => $oUser->getId(),
				'wds_filename' => $sName,
				'wds_path' => $sTmpFile,
				'wds_ts' => wfTimestamp()
			)
		);
		if( $oRes === false ) {
			return true;
		}
		return false;
	}

	/**
	 * Uploads the file to the wiki
	 * when that file is closed
	 *
	 * @param bool $bSuccess
	 * @param \Sabre\DAV\Locks\LockInfo $oLockInfo
	 * @return true
	 */
	public static function onWebDAVLocksUnlock( $bSuccess, $oLockInfo ) {

		self::publishFileToWiki( $oLockInfo->uri );
		return true;
	}

	/**
	 * In case there are expired locks
	 * temp file must be uploaded before lock is deleted
	 *
	 * @param string $sUri
	 * @param int $iUser
	 * @return true
	 */
	public static function onWebDAVGetLocksExpired( $sUri, $iUser ) {
		self::publishFileToWiki( $sUri, $iUser );
		return true;
	}

	protected static function publishFileToWiki( $sUri, $iUserId = null ) {
		$sFilename = self::normalizeName( WebDAVHelper::getFilenameFromUrl( $sUri ) );
		if( $iUserId ) {
			$oUser = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $iUserId );
		} else {
			$oUser = RequestContext::getMain()->getUser();
		}
		$oDB = wfGetDB( DB_PRIMARY );
		$oSave = $oDB->selectRow(
			'bs_webdav_saves',
			array( 'wds_path' ),
			array(
				'wds_user_id' => $oUser->getId(),
				'wds_filename' => $sFilename
			)
		);

		if( $oSave === false ) {
			return true;
		}
		$sPath = $oSave->wds_path;
		WebDAVFileFile::publishToWiki( $sPath, $sFilename );
		$oDB->delete(
			'bs_webdav_saves',
			array(
				'wds_user_id' => $oUser->getId(),
				'wds_filename' => $sFilename
			)
		);
		return true;
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public static function normalizeName( $name ) {
		return str_replace( ' ', '_', $name );
	}
}

