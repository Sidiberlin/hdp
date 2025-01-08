<?php

namespace BlueSpice\QrCode\Hook\BSUEModulePDFFindFiles;

use BlueSpice\UEModulePDF\Hook\IBSUEModulePDFFindFiles;
use BsFileSystemHelper;
use MediaWiki\Utils\UrlUtils;

class AddQrCode implements IBSUEModulePDFFindFiles {

	/**
	 *
	 * @var UrlUtils
	 */
	private $urlUtils = null;

	/**
	 *
	 * @param UrlUtils $urlUtils
	 */
	public function __construct( UrlUtils $urlUtils ) {
		$this->urlUtils = $urlUtils;
	}

	/**
	 * @inheritDoc
	 */
	public function onBSUEModulePDFFindFiles(
		$sender,
		$imageElement,
		&$absoluteFileSystemPath,
		&$fileName,
		$type
	): bool {
		if ( $type !== 'images' ) {
			return true;
		}
		$imgClass = $imageElement->getAttribute( 'class' );
		if ( strpos( $imgClass, 'qrCodeImg' ) === false ) {
			return true;
		}

		$origUrl = $imageElement->getAttribute( 'data-orig-src' );

		$origUrl = $this->urlUtils->expand( $origUrl );
		$parseUrl = $this->urlUtils->parse( $origUrl );

		$params = wfCgiToArray( $parseUrl['query'] );
		$pagename = $params['pagename'];
		$query = $params['query'];
		$size = $params['size'];

		$id = md5( $pagename . $query );
		$fileName = $id . '.png';

		$file = BsFileSystemHelper::getFileFromRepoName(
			$fileName,
			'QrCode'
		);

		if ( $file ) {
			$sourcePath = 'images/bluespice/QrCode/';
			$src = $sourcePath . $fileName;
			$absoluteFileSystemPath = $src;
			$imageElement->setAttribute( 'src', 'images/' . $fileName );
			$imageElement->setAttribute( 'width', ( $size / 60 ) . 'cm' );
			$imageElement->setAttribute( 'height', ( $size / 60 ) . 'cm' );
		}

		return true;
	}
}
