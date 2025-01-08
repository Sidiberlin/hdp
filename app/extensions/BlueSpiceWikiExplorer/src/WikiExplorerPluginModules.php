<?php

namespace BlueSpice\WikiExplorer;

use MWStake\MediaWiki\Component\ManifestRegistry\ManifestAttributeBasedRegistry;

class WikiExplorerPluginModules {

	/**
	 * @return array
	 */
	public static function getPluginModules(): array {
		$registry = new ManifestAttributeBasedRegistry(
			'BlueSpiceWikiExplorerPluginModules'
		);

		$pluginModules = [];
		foreach ( $registry->getAllKeys() as $key ) {
			$moduleName = $registry->getValue( $key );
			$pluginModules[] = $moduleName;
		}

		return $pluginModules;
	}
}
