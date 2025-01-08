<?php

namespace MediaWiki\Extension\ImportOfficeFiles\MediaWiki\HookHandler;

use Config;
use MediaWiki\Extension\ImportOfficeFiles\ModuleFactory;
use MediaWiki\Permissions\PermissionManager;

class Skin {

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var PermissionManager
	 */
	private $permissionManager;

	/**
	 * @param Config $config
	 * @param PermissionManager $permissionManager
	 */
	public function __construct( Config $config, PermissionManager $permissionManager ) {
		$this->config = $config;
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @param \SkinTemplate $skinTemplate
	 * @param array &$links
	 */
	public function onSkinTemplateNavigation__Universal( $skinTemplate, &$links ): void {
		$user = $skinTemplate->getUser();
		if ( !$this->permissionManager->userHasRight( $user, 'createpage' ) ) {
			return;
		}

		$links['actions']['import-office-file'] = [
			'text' => $skinTemplate->getContext()
				->msg( "importofficefiles-ui-action-import-msword-text" )->text(),
			'title' => $skinTemplate
				->getContext()->msg( "importofficefiles-ui-action-import-msword-title" )->text(),
			'href' => '',
			'class' => 'import-office-file'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModules( "ext.importofficefiles.bootstrap" );

		$moduleFactory = new ModuleFactory();
		$supportedMimeTypes = $moduleFactory->getSupportedMimeTypes();
		$out->addJsConfigVars( 'importOfficeFilesSupportedMimeTypes', $supportedMimeTypes );
	}

}
