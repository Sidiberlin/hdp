<?php

namespace BlueSpice\ExtendedStatistics\Compatibility;

use MediaWiki\MediaWikiServices;
use Wikimedia\ObjectFactory\ObjectFactory;

class ObjectFactoryWithServices extends ObjectFactory {
	/** @var MediaWikiServices */
	private $services;

	/**
	 * ObjectFactoryWithServices constructor.
	 * @param MediaWikiServices $services
	 */
	public function __construct( MediaWikiServices $services ) {
		$this->services = $services;
	}

	/**
	 * @param array $specs
	 * @param array $options
	 * @return stdClass
	 * @throws \ReflectionException
	 */
	public function createObject( $specs, array $options = [] ) {
		$specs = $this->convertToServices( $specs );
		return static::getObjectFromSpec( $specs, $options );
	}

	/**
	 * @param array $specs
	 * @return array
	 */
	private function convertToServices( $specs ) {
		if ( !is_array( $specs ) ) {
			return $specs;
		}

		// This is required for <MW1.34 only
		if ( isset( $specs['services'] ) ) {
			if ( !isset( $specs['args'] ) ) {
				$specs['args'] = [];
			}
			foreach ( array_reverse( $specs['services'] ) as $serviceKey ) {
				array_unshift( $specs['args'], $this->services->getService( $serviceKey ) );
			}
			unset( $specs['services'] );
		}

		return $specs;
	}
}
