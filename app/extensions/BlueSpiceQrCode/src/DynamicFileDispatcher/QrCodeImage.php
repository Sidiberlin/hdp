<?php

namespace BlueSpice\QrCode\DynamicFileDispatcher;

use BlueSpice\DynamicFileDispatcher\File;
use BsFileSystemHelper;
use Endroid\QrCode\QrCode;
use MediaWiki\MediaWikiServices;
use MWException;
use WebResponse;

class QrCodeImage extends File {

	/**
	 * Sets the headers for given \WebResponse
	 * @param WebResponse $response
	 * @return void
	 */
	public function setHeaders( WebResponse $response ) {
		$response->header(
			'Content-type: ' . $this->getMimeType(),
			true
		);

		readfile( $this->getSourcePath() );
	}

	/**
	 *
	 * @return string
	 */
	private function getSourcePath() {
		$sourcePath = 'images/bluespice/QrCode/';
		$params = $this->dfd->getParams();
		$pagename = $params['pagename'];
		$query = $params['query'];
		$size = $params['size'];
		$id = md5( $pagename . $query );
		$filename = $id . ".png";
		$file = BsFileSystemHelper::getFileFromRepoName(
			$filename,
			'QrCode'
		);

		if ( $file instanceof File ) {
			return $sourcePath . $filename;
		}
		$titleFactory = MediaWikiServices::getInstance()->getTitleFactory();
		$title = $titleFactory->newFromText( $pagename );
		$url = $title->getFullURL( $query );

		$qrCode = new QrCode( $url );
		$qrCode->setSize( $size );
		$qrCodeSrc = $qrCode->writeString();

		$status = BsFileSystemHelper::saveToDataDirectory(
			$filename,
			$qrCodeSrc,
			'QrCode'
		);
		if ( !$status->isGood() ) {
			throw new MWException(
				'FATAL: QrCode could not be saved! ' . $status->getMessage()
			);
		}

		$file = BsFileSystemHelper::getFileFromRepoName(
			$filename,
			'QrCode'
		);
		return $sourcePath . $filename;
	}

	/**
	 *
	 * @return string
	 */
	public function getMimeType() {
		return 'image/png';
	}

}
