<?php

namespace BlueSpice\ProDistributionConnector\Math;

use MediaWiki\Extension\Math\Render\RendererFactory;

class SVGProvider {

	/** @var RendererFactory */
	private $rendererFactory;

	public function __construct( RendererFactory $rendererFactory ) {
		$this->rendererFactory = $rendererFactory;
	}

	/**
	 * @param string $hash
	 * @return string|null
	 */
	public function getSvg( $hash ): string {
		$renderer = $this->rendererFactory->getFromHash( $hash );
		$success = $renderer->render();
		if ( !$success ) {
			return null;
		}

		$svg = $renderer->getSvg();
		return $svg;
	}
}
