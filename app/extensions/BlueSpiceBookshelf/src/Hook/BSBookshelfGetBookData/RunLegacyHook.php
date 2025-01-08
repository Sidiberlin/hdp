<?php

namespace BlueSpice\Bookshelf\Hook\BSBookshelfGetBookData;

use BlueSpice\Bookshelf\Hook\BSBookshelfGetBookData;

/**
 * @deprecated since version 3.3 - use hook 'BSBookshelfGetBookData' instead
 */
class RunLegacyHook extends BSBookshelfGetBookData {
	/**
	 *
	 * @return bool
	 */
	protected function skipProcessing() {
		return empty( $GLOBALS['wgHooks']['BSBookshelfBookUI'] );
	}

	/**
	 * @deprecated since version 3.3 - use hook 'BSBookshelfGetBookData' instead
	 */
	protected function doProcess() {
		wfDebugLog( 'bluespice-deprecations', __METHOD__, 'private' );
		$this->getServices()->getHookContainer()->run(
			'BSBookshelfBookUI',
			[
				null,
				$this->getContext()->getOutput(),
				$this->bookData
			],
			[ 'deprecatedVersion' => '3.3' ]
		);
	}
}
