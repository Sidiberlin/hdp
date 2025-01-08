<?php

namespace ChatBot;

use InvalidArgumentException;
use UnexpectedValueException;
use Wikimedia\ObjectFactory\ObjectFactory;

class AdminModuleFactory {

	/** @var array */
	private array $attribute;

	/** @var ObjectFactory */
	private ObjectFactory $objectFactory;

	/**
	 * @param array $attribute
	 * @param ObjectFactory $objectFactory
	 */
	public function __construct( array $attribute, ObjectFactory $objectFactory ) {
		$this->attribute = $attribute;
		$this->objectFactory = $objectFactory;
	}

	/**
	 * @return array
	 */
	public function getModuleKeys(): array {
		return array_keys( $this->attribute );
	}

	/**
	 * @return array
	 */
	public function getModules(): array {
		$modules = [];
		foreach ( $this->attribute as $key => $value ) {
			$modules[$key] = $this->getModule( $key );
		}

		return $modules;
	}

	/**
	 * @param string $name
	 * @return IAdminModule
	 */
	public function getModule( string $name ): IAdminModule {
		if ( !isset( $this->attribute[$name] ) ) {
			throw new InvalidArgumentException( 'Module not found' );
		}
		$spec = $this->attribute[$name];
		$module = $this->objectFactory->createObject( $spec );
		if ( !$module instanceof IAdminModule ) {
			throw new UnexpectedValueException( "Invalid module $name" );
		}

		return $module;
	}
}
