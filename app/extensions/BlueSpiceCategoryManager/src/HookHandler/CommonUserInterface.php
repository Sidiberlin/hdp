<?php

namespace BlueSpice\CategoryManager\HookHandler;

use BlueSpice\CategoryManager\GlobalActionsEditing;
use MWStake\MediaWiki\Component\CommonUserInterface\Hook\MWStakeCommonUIRegisterSkinSlotComponents;

class CommonUserInterface implements MWStakeCommonUIRegisterSkinSlotComponents {

	/**
	 * @inheritDoc
	 */
	public function onMWStakeCommonUIRegisterSkinSlotComponents( $registry ): void {
		$registry->register(
			'GlobalActionsEditing',
			[
				'special-bluespice-categorymanager' => [
					'factory' => static function () {
						return new GlobalActionsEditing();
					}
				]
			]
		);
	}
}
