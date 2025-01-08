<?php

namespace MediaWiki\Extension\CollabPads\Hook;

use BlueSpice\Discovery\ITemplateDataProvider;
use Title;

interface CollabPadsAfterAddContentActionHook {

	/**
	 * Allow extensions to unregister 'ca-collabpad' content action
	 *
	 * @param Title $title
	 * @param ITemplateDataProvider $registry
	 */
	public function onCollabPadsAfterAddContentAction( Title $title, ITemplateDataProvider $registry ): void;
}
