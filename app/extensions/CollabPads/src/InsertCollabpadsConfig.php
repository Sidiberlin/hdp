<?php

namespace MediaWiki\Extension\CollabPads;

use MediaWiki\MediaWikiServices;
use MWStake\MediaWiki\Component\ManifestRegistry\ManifestAttributeBasedRegistry;

class InsertCollabpadsConfig {
	/**
	 * @return array
	 */
	public static function makeConfig() {
		$mainConfig = MediaWikiServices::getInstance()->getMainConfig();
		$backendServiceURL = $mainConfig->get( 'CollabPadsBackendServiceURL' );
		return [
			'backendServiceURL' => $backendServiceURL
		];
	}

	/**
	 * @return array
	 */
	public static function getPluginModules() {
		$registry = new ManifestAttributeBasedRegistry(
			'CollabPadsPluginModules'
		);

		$pluginModules = [];
		foreach ( $registry->getAllKeys() as $key ) {
			$moduleName = $registry->getValue( $key );
			$pluginModules[] = $moduleName;
		}

		return $pluginModules;
	}
}
