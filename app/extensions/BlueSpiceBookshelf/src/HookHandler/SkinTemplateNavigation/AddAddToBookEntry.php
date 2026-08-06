<?php

namespace BlueSpice\Bookshelf\HookHandler\SkinTemplateNavigation;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Title\TitleFactory;
use SkinTemplate;

class AddAddToBookEntry implements SkinTemplateNavigation__UniversalHook {

	/** @var PermissionManager */
	private $permissionManager;

	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param PermissionManager $permissionManager
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( PermissionManager $permissionManager, TitleFactory $titleFactory ) {
		$this->permissionManager = $permissionManager;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @param SkinTemplate $sktemplate
	 * @return bool
	 */
	protected function skipProcessing( SkinTemplate $sktemplate ) {
		$user = $sktemplate->getUser();
		$title = $sktemplate->getTitle();
		if ( !$title->exists() ) {
			return true;
		}
		if ( !$title->isContentPage() ) {
			return true;
		}
		// Check if user has right to edit in NS Book
		$dummyBookTitle = $this->titleFactory->newFromText( 'Dummy', NS_BOOK );
		if ( !$this->permissionManager->userCan( 'edit', $user, $dummyBookTitle ) ) {
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
