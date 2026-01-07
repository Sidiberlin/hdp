<?php

namespace BlueSpice\ProDistributionConnector\PDFCreator\Utility;

use BlueSpice\ProDistributionConnector\Math\SVGProvider;
use DOMDocument;
use DOMXPath;
use MediaWiki\Config\Config;
use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\Extension\PDFCreator\Utility\WikiFileResource;
use MediaWiki\Utils\UrlUtils;

class ImageFinder {

	/** @var UrlUtils */
	private $urlUtils;

	/** @var RendererFactory */
	private $rendererFactory;

	/** @var Config */
	private $config;

	/** @var array */
	protected $data = [];

	public function __construct( UrlUtils $urlUtils, RendererFactory $rendererFactory, Config $config ) {
		$this->urlUtils = $urlUtils;
		$this->rendererFactory = $rendererFactory;
		$this->config = $config;
	}

	/**
	 * @param array $pages
	 * @param array $resources
	 * @return array
	 */
	public function execute( array $pages, array $resources = [] ): array {
		$files = [];

		foreach ( $resources as $filename => $resourcePath ) {
			$this->data[$filename] = [
				'src' => [],
				'absPath' => $resourcePath,
				'filename' => $filename
			];
		}

		foreach ( $pages as $page ) {
			$dom = $page->getDOMDocument();
			$this->find( $dom );
		}

		foreach ( $this->data as $data ) {
			$files[] = new WikiFileResource(
				$data['src'],
				$data['absPath'],
				$data['filename']
			);
		}

		return $files;
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	protected function find( DOMDocument $dom ): void {
		$xpath = new DOMXPath( $dom );
		$images = $xpath->query(
			'//img',
			$dom
		);
		$uploadDir = $this->config->get( 'UploadDirectory' );

		/** @var DOMElement */
		foreach ( $images as $image ) {
			if ( !$image->hasAttribute( 'class' ) ) {
				continue;
			}
			$imgClass = $image->getAttribute( 'class' );
			if ( strpos( $imgClass, 'mwe-math-fallback-image' ) === false ) {
				continue;
			}

			if ( !$image->hasAttribute( 'src' ) ) {
				continue;
			}
			$src = $image->getAttribute( 'src' );

			$origUrl = $this->urlUtils->expand( $src );
			$parseUrl = $this->urlUtils->parse( $origUrl );
			$params = wfCgiToArray( $parseUrl['query'] );
			$hash = $params['hash'];
			$svgPathname = "$uploadDir/$hash.svg";
			$svgProvider = new SVGProvider( $this->rendererFactory );
			$svgXML = $svgProvider->getSvg( $hash );

			if ( !$svgXML ) {
				continue;
			}

			$svgDOM = new DOMDocument();
			$svgDOM->loadXML( $svgXML );
			$svgDOM->save( $svgPathname );

			$filename = "$hash.svg";
			$absPath = $svgPathname;

			if ( !isset( $this->data[$filename] ) ) {
				$this->data[$filename] = [
					'src' => [ $src ],
					'absPath' => $absPath,
					'filename' => str_replace( ':', '_', $filename )
				];
			} elseif ( $this->data[$filename]['absPath'] === $absPath ) {
				$urls = &$this->data[$filename]['src'];
				if ( !in_array( $src, $urls ) ) {
					$urls[] = $src;
				}
			}

			$width = $params['width'] ? (int)$params['width'] : 32;
			$height = $params['height'] ? (int)$params['height'] : 32;
			$image->setAttribute( 'src', 'images/' . "$hash.svg" );
			$image->setAttribute( 'width', ( $width / 150 ) . 'cm' );
			$image->setAttribute( 'height', ( $height / 150 ) . 'cm' );
		}
	}
}
