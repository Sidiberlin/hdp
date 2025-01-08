<?php

namespace BlueSpice\ProDistributionConnector\Math;

use MediaWiki\Extension\Math\MathRenderer;

class SVGProvider {

	/**
	 * See `\MathRenderer::getSvg` from "Extension:Math"
	 * @param string $hash
	 * @return string
	 */
	public function getSvg( $hash ): string {
		$renderer = MathRenderer::getRenderer( '', [], 'mathml' );
		$renderer->setMd5( $hash );
		// Required to actually load the data
		$renderer->isInDatabase();
		$renderer->render();
		$svg = $renderer->getSvg();

		return $svg;
	}
}
