<?php

namespace BlueSpice\Expiry\HookHandler;

use BlueSpice\Expiry\GlobalActionsEditing;
use MWStake\MediaWiki\Component\CommonUserInterface\Hook\MWStakeCommonUIRegisterSkinSlotComponents;

class CommonUserInterface implements MWStakeCommonUIRegisterSkinSlotComponents {

	/**
	 * @inheritDoc
	 */
	public function onMWStakeCommonUIRegisterSkinSlotComponents( $registry ): void {
		$registry->register(
			'GlobalActionsEditing',
			[
				'special-bluespice-expiry' => [
					'factory' => static function () {
						return new GlobalActionsEditing();
					}
				]
			]
		);
	}

}
