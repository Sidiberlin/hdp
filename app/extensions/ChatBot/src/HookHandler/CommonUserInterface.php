<?php

namespace ChatBot\HookHandler;

use BlueSpice\Discovery\SkinSlotRenderer\DataBeforeContentSkinSlotRenderer;
use ChatBot\Component\ChatBot;
use ChatBot\Util\RoleLookup;
use Config;
use MWStake\MediaWiki\Component\CommonUserInterface\Hook\MWStakeCommonUIRegisterSkinSlotComponents;

class CommonUserInterface implements MWStakeCommonUIRegisterSkinSlotComponents {
	/** @var Config */
	private Config $config;

	/** @var RoleLookup */
	private RoleLookup $roleLookup;

	/**
	 * @param Config $config
	 * @param RoleLookup $roleLookup
	 */
	public function __construct( Config $config, RoleLookup $roleLookup ) {
		$this->config = $config;
		$this->roleLookup = $roleLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function onMWStakeCommonUIRegisterSkinSlotComponents( $registry ): void {
		$registry->register(
			DataBeforeContentSkinSlotRenderer::REG_KEY, [
				'deepset-chatbot' => [
					'factory' => function () {
						return new ChatBot( $this->config, $this->roleLookup );
					},
					'position' => 50
				]
			]
		);
	}
}
