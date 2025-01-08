<?php

namespace BlueSpice\ProDistributionConnector;

use ExtensionRegistry;

class Extension extends \BlueSpice\Extension {

	public static function onRegistration() {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'PDFEmbed' ) ) {
			$GLOBALS['wgContentDropletsDroplets']['pdf-embed'] = [
				"class" => "\\BlueSpice\\ProDistributionConnector\\ContentDroplets\\PDFEmbedDroplet"
			];
		}
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Math' ) ) {
			$GLOBALS['wgContentDropletsDroplets']['math-form'] = [
				"class" => "\\BlueSpice\\ProDistributionConnector\\ContentDroplets\\MathFormDroplet"
			];
			$GLOBALS['wgContentDropletsDroplets']['chem-form'] = [
				"class" => "\\BlueSpice\\ProDistributionConnector\\ContentDroplets\\ChemFormDroplet"
			];
		}
	}
}
