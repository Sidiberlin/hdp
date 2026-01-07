<?php

namespace BlueSpice\Bookshelf\HookHandler\SkinTemplateNavigation;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Permissions\PermissionManager;
use SkinTemplate;

class AddAddToBookEntry implements SkinTemplateNavigation__UniversalHook {

	/** @var PermissionManager */
	private $permissionManager;

	/**
	 * @param PermissionManager $permissionManager
	 */
	public function __construct( PermissionManager $permissionManager ) {
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @param SkinTemplate $sktemplate
	 * @return bool
	 */
	protected function skipProcessing( SkinTemplate $sktemplate ) {
		$title = $sktemplate->getTitle();
		if ( !$title->exists() ) {
			return true;
		}
		if ( !$title->isContentPage() ) {
			return true;
		}
		if ( !$this->permissionManager->userCan( 'edit', $sktemplate->getUser(), $title ) ) {
			return true;
		}
		return false;
	}

	/**
	 * // phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( $this->skipProcessing( $sktemplate ) ) {
			return;
		}

		$links['actions']['bookshelf-add-to-book'] = [
			'text' => $sktemplate->msg( 'bs-bookshelf-actionmenuentry-addtobook' )->text(),
			'href' => '#',
			'class' => false,
			'id' => 'ca-bookshelf-add-to-book',
			'position' => 50,
		];
		$sktemplate->getOutput()->addModules( 'ext.bluespice.bookshelf.addToBook' );
	}
}
