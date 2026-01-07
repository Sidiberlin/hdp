<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

use Exception;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;

class ModuleFactory {

	/**
	 * @param Workspace $workspace
	 * @return IModule|null
	 */
	public function getModule( $workspace ): ?IModule {
		$moduleSpecs = $this->getModuleSpecs();

		$module = null;
		$objectFactory = MediaWikiServices::getInstance()->getObjectFactory();
		foreach ( $moduleSpecs as $name => $specs ) {
			$curModule = $objectFactory->createObject( $specs );

			if ( !( $curModule instanceof IModule ) ) {
				throw new Exception( "$name is not instance of IModule" );
			}

			$curModule->setWorkspace( $workspace );
			if ( $curModule->canHandle() ) {
				$module = $curModule;
				break;
			}
		}

		return $module;
	}

	/**
	 * @return array
	 */
	public function getSupportedMimeTypes(): array {
		$moduleSpecs = $this->getModuleSpecs();

		$mimeTypes = [];
		$objectFactory = MediaWikiServices::getInstance()->getObjectFactory();
		foreach ( $moduleSpecs as $name => $specs ) {
			$curModule = $objectFactory->createObject( $specs );

			if ( !( $curModule instanceof IModule ) ) {
				throw new Exception( "$name is not instance of IModule" );
			}

			$mimeTypes = array_merge(
				$mimeTypes,
				$curModule->getSupportedMimeTypes()
			);
		}

		return $mimeTypes;
	}

	/**
	 * @return array
	 */
	private function getModuleSpecs(): array {
		$extensionRegistry = ExtensionRegistry::getInstance();
		$moduleSpecs = $extensionRegistry->getAttribute(
			'ImportOfficeFilesModuleRegistry'
		);

		return $moduleSpecs;
	}
}
