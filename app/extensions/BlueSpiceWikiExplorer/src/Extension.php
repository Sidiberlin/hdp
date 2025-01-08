<?php

namespace BlueSpice\WikiExplorer;

class Extension extends \BlueSpice\Extension {

	/**
	 * Adds Special:WikiExplorer link to wiki wide widget
	 * @param UserSidebar $oUserSidebar
	 * @param User $oUser
	 * @param array &$aLinks
	 * @param string &$sWidgetTitle
	 * @return bool
	 */
	public function onBSUserSidebarGlobalActionsWidgetGlobalActions( UserSidebar $oUserSidebar,
		User $oUser, &$aLinks, &$sWidgetTitle ) {
		$oSpecialWikiExplorer = $this->services->getSpecialPageFactory()
			->getPage( 'WikiExplorer' );
		if ( !$oSpecialWikiExplorer ) {
			return true;
		}
		$aLinks[] = [
			'target' => $oSpecialWikiExplorer->getPageTitle(),
			'text' => $oSpecialWikiExplorer->getDescription(),
			'attr' => [],
			'position' => 900,
			'permissions' => [
				'read'
			],
		];
		return true;
	}
}
