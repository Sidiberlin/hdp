<?php

namespace BlueSpice\UniversalExport\HookHandler;

use BlueSpice\UniversalExport\ExportDialogButtonComponent;

class DiscoverySkinHandler {

	/**
	 * @inheritDoc
	 */
	public function onMWStakeCommonUIRegisterSkinSlotComponents( $registry ): void {
		$registry->register(
			'ToolbarPanel',
			[
				'export' => [
					'factory' => static function () {
						return new ExportDialogButtonComponent();
					}
				]
			]
		);
	}

}
