<?php

namespace MediaWiki\Extension\ImportOfficeFiles\Integration;

trait BlueSpiceFarmingTrait {
	/**
	 * @return bool
	 */
	private function isFarmingInstance(): bool {
		return defined( 'FARMER_CALLED_INSTANCE' ) && FARMER_CALLED_INSTANCE !== '';
	}

	/**
	 * @param array &$params
	 *
	 * @return void
	 */
	protected function extendParams( array &$params ) {
		if ( !$this->isFarmingInstance() ) {
			return;
		}
		$params[] = '--sfr=' . FARMER_CALLED_INSTANCE;
	}
}
