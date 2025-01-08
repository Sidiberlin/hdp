<?php

class BSApiFileHistoryStore extends BSApiExtJSStoreBase {
	protected function makeData($sQuery = '') {
		$aResult = array();
		if( empty( $sQuery ) ) {
			return $aResult;
		}
		$oTitle = Title::newFromText( $sQuery );
		if( $oTitle instanceof Title && in_array( $oTitle->getNamespace(), array( NS_FILE, NS_MEDIA ) ) ) {
			$sQuery = $oTitle->getText();
		}
		$oFile = $this->services->getRepoGroup()->findFile( $sQuery );
		$aFileVersions = $oFile->getHistory();
		$aFileVersions[] = $oFile;

		foreach( $aFileVersions as $oFileVersion ) {
			if( $oFileVersion instanceof File === false ) {
				continue;
			}

			//Trying to be as close to BSApiFileBackendStore response as possible
			$oRow = new stdClass();
			$oRow->file_url = $oFileVersion->getUrl();
			$oRow->file_full_url = $oFileVersion->getFullUrl();
			$oRow->file_thumbnail_url = $oFileVersion->getThumbUrl();
			$oRow->file_metadata = serialize( $oFileVersion->getMetadataArray() );

			$uploaderUserId = -1;
			$uploaderUserName = '';
			$uploaderUser = $oFileVersion->getUploader();
			if ( $uploaderUser !== null ) {
				$uploaderUserId = $uploaderUser->getId();
				$uploaderUserName = $uploaderUser->getName();
			}

			$oRow->file_user_text = $uploaderUserName;

			$oRow->file_user_link = $this->services->getLinkRenderer()->makeLink(
				Title::makeTitle( NS_USER, $uploaderUserName ),
				$uploaderUserName
			);
			$oRow->file_user = $uploaderUserId;
			$oRow->file_long_desc = $oFileVersion->getLongDesc();
			$oRow->file_description = $oFileVersion->getDescription();
			$oRow->file_description_url = $oFileVersion->getDescriptionUrl();
			$oRow->file_size = (int)$oFileVersion->getSize();
			$oRow->file_timestamp = $this->getLanguage()->userAdjust( $oFileVersion->getTimestamp() );
			$oRow->file_width = (int)$oFileVersion->getWidth();
			$oRow->file_height = (int)$oFileVersion->getHeight();

			$aResult[] = $oRow;
		}

		return $aResult;
	}
}
