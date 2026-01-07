<?php

namespace BlueSpice\Bookshelf\HookHandler\SkinTemplateNavigation;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Title\TitleFactory;
use SkinTemplate;

class AddNewBook implements SkinTemplateNavigation__UniversalHook {

	/** @var PermissionManager */
	private $permissionManager;

	/** @var TitleFactory */
	private $titleFactory;

	/**
	 *
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
		// Check if user can create pages in NS Book
		$title = $this->titleFactory->newFromText( 'Dummy', NS_BOOK );

		$userCan = $this->permissionManager->userCan( 'edit', $user, $title );
		if ( !$userCan ) {
			return true;
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( $this->skipProcessing( $sktemplate ) ) {
			return;
		}
		$title = $sktemplate->getTitle();
		if ( $title->isSpecial( 'Books' ) ) {
			$createBookAction = [
				'text' => $sktemplate->msg( 'bs-bookshelf-actionmenuentry-create-new-book' )->text(),
				'title' => $sktemplate->msg( 'bs-bookshelf-actionmenuentry-create-new-book' )->text(),
				'class' => 'new-book-action',
				'href' => ''
			];
			// actions_primary
			$links['actions']['bookshelf-create-new-book'] = $createBookAction;
			$links['actions']['bookshelf-create-new-book']['id'] = 'ca-bookshelf-actions-primary-new-book';
			$links['actions']['bookshelf-create-new-book']['position'] = 1;
		}
		$newBookAction = [
			'text' => $sktemplate->msg( 'bs-bookshelf-actionmenuentry-new-book' )->text(),
			'title' => $sktemplate->msg( 'bs-bookshelf-actionmenuentry-new-book' )->text(),
			'href' => '',
			'class' => 'new-book-action'
		];
		// panel/create
		$links['actions']['bookshelf-new-book'] = $newBookAction;
		$links['actions']['bookshelf-new-book']['id'] = 'ca-bookshelf-panel-create-new-book';

		$sktemplate->getOutput()->addModules( 'ext.bluespice.bookshelf.createNewBook' );
	}
}
