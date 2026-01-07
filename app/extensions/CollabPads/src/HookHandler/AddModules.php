<?php

namespace MediaWiki\Extension\CollabPads\HookHandler;

use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use Skin;

class AddModules implements BeforePageDisplayHook {

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$title = $out->getTitle();
		if ( !$title ) {
			return;
		}

		$isHistory = $out->getRequest()->getText( 'action', 'view' ) === 'history';
		$isRecentChanges = $title->isSpecial( 'Recentchanges' );
		$isContributions = $title->isSpecial( 'Contributions' );
		if ( $isHistory || $isRecentChanges || $isContributions ) {
			$out->addModuleStyles( 'collabpad.ui.participants.styles' );
		}
	}
}
