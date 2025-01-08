<?php

namespace BlueSpice\ProDistributionConnector\HookHandler;

use BlueSpice\ProDistributionConnector\Math\SVGProvider;
use BlueSpice\UEModulePDF\Hook\IBSUEModulePDFFindFiles;
use BlueSpice\UEModulePDF\HookHandler\BSUEModulePDFFindFilesStub;
use DOMDocument;
use DOMElement;
use MediaHandlerFactory;
use MediaWiki\MediaWikiServices;
use SvgHandler;

class PdfExportConvertMathSVGtoPNG implements IBSUEModulePDFFindFiles {

	/**
	 *
	 * @var SvgHandler
	 */
	private $svgHandler = null;

	/**
	 *
	 * @var SVGProvider
	 */
	private $svgProvider = null;

	/**
	 *
	 * @var bool
	 */
	private $enabled = true;

	/**
	 *
	 * @var string
	 */
	private $workingDir = '';

	/**
	 *
	 * @var DOMDocument
	 */
	private $svgDOM = null;

	/**
	 *
	 * @param SvgHandler $svgHandler
	 * @param SVGProvider $svgProvider
	 * @param string $workingDir
	 */
	public function __construct( $svgHandler, $svgProvider, $workingDir ) {
		$this->svgHandler = $svgHandler;
		$this->svgProvider = $svgProvider;
		$this->workingDir = $workingDir;
	}

	/**
	 *
	 * @param MediaHandlerFactory $mediaHandlerFactory
	 * @return IBSUEModulePDFFindFiles
	 */
	public static function factory( $mediaHandlerFactory ) {
		if ( !class_exists( 'MediaWiki\Extension\Math\MathRenderer' ) ) {
			// Basically a NULL handler
			return new BSUEModulePDFFindFilesStub();
		}

		$svgHandler = $mediaHandlerFactory->getHandler( 'image/svg' );
		$svgProvider = new SVGProvider();
		$workingDir = wfTempDir();

		return new static( $svgHandler, $svgProvider, $workingDir );
	}

	/**
	 * @inheritDoc
	 */
	public function onBSUEModulePDFFindFiles(
		$sender,
		$imgEl,
		&$absFSpath,
		&$fileName,
		$type ): bool {
		if ( $type !== 'images' ) {
			return true;
		}

		$imgClass = $imgEl->getAttribute( 'class' );
		if ( strpos( $imgClass, 'mwe-math-fallback-image' ) === false ) {
			return true;
		}

		$hash = $this->getHashFromImgElement( $imgEl );

		$pngPathname = $this->workingDir . "/$hash.png";
		if ( !file_exists( $pngPathname ) ) {
			$svgPathname = $this->workingDir . "/$hash.svg";
			$svgXML = $this->svgProvider->getSvg( $hash );

			$this->svgDOM = new DOMDocument();
			$this->svgDOM->loadXML( $svgXML );

			$height = $this->getImageDimension( 'height' );
			$width = $this->getImageDimension( 'width' );

			$this->svgDOM->save( $svgPathname );

			$this->svgHandler->rasterize( $svgPathname, $pngPathname, $width, $height );
		} else {
			list( $width, $height ) = getimagesize( $pngPathname );
		}

		$absFSpath = $pngPathname;
		$fileName = wfBaseName( $pngPathname );
		$imgEl->setAttribute( 'src', 'images/' . $fileName );
		$imgEl->setAttribute( 'style', '' );
		$imgEl->setAttribute( 'width', ( $width / 150 ) . 'cm' );
		$imgEl->setAttribute( 'height', ( $height / 150 ) . 'cm' );

		return true;
	}

	/**
	 *
	 * @param DOMElement $imgEl
	 * @return string
	 */
	private function getHashFromImgElement( $imgEl ) {
		$urlUtils = MediaWikiServices::getInstance()->getUrlUtils();
		$origUrl = $imgEl->getAttribute( 'data-orig-src' );
		$origUrl = $urlUtils->expand( $origUrl );
		$parseUrl = $urlUtils->parse( $origUrl );
		$parsedQueyString = wfCgiToArray( $parseUrl['query'] );
		$hash = $parsedQueyString['hash'];

		return $hash;
	}

	/**
	 *
	 * @param string $dimension
	 * @return int
	 */
	private function getImageDimension( $dimension ) {
		$val = str_replace(
			'ex',
			'',
			$this->svgDOM->documentElement->getAttribute( $dimension )
		);
		$val = (int)$val * 20;

		return $val;
	}
}
