<?php

namespace BlueSpice\Expiry\HookHandler;

use Config;
use MediaWiki\Extension\StandardDialogs\Hook\StandardDialogsRegisterPageInfoPanelModules;
use MediaWiki\ResourceLoader\Context as ResourceLoaderContext;

class StandardDialogs implements StandardDialogsRegisterPageInfoPanelModules {

	/**
	 * @inheritDoc
	 */
	public function onStandardDialogsRegisterPageInfoPanelModules(
		&$modules,
		ResourceLoaderContext $context,
		Config $config ): void {
		$modules[] = "ext.bluespice.expiry.dialoginfo.pages";
	}
}
