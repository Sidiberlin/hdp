<?php

namespace BlueSpice\ProDistributionConnector\ConfigDefinition;

use BlueSpice\ConfigDefinition;
use BlueSpice\ProDistributionConnector\EditionProvider;
use Config;
use IContextSource;
use MediaWiki\MediaWikiServices;

class LicenseKey extends ConfigDefinition\StringSetting {
	/** @var EditionProvider */
	private $editionProvider;

	/**
	 * @inheritDoc
	 */
	public static function getInstance( $context, $config, $name ) {
		$editionProvider = MediaWikiServices::getInstance()->getService( 'BlueSpiceEditionProvider' );
		return new static( $context, $config, $name, $editionProvider );
	}

	/**
	 * @param IContextSource $context
	 * @param Config $config
	 * @param string $name
	 * @param EditionProvider $editionProvider
	 */
	public function __construct( $context, $config, $name, EditionProvider $editionProvider ) {
		parent::__construct( $context, $config, $name );
		$this->editionProvider = $editionProvider;
	}

	/**
	 * @return string[]
	 */
	public function getPaths() {
		return [
				static::MAIN_PATH_FEATURE . '/' . static::FEATURE_SYSTEM . '/BlueSpiceProDistributionConnector',
				static::MAIN_PATH_EXTENSION . '/BlueSpiceProDistributionConnector/' . static::FEATURE_SYSTEM ,
				static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . '/BlueSpiceProDistributionConnector',
			];
	}

	/**
	 * @return string
	 */
	public function getLabelMessageKey() {
		return "bs-pro-distribution-config-license-key";
	}

	/**
	 * @return bool
	 */
	public function isHidden() {
		return !$this->editionProvider->checkIsLicenseConfigurable();
	}
}
