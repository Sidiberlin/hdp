<?php

namespace BlueSpice\Bookshelf\HookHandler\SkinTemplateNavigation;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\MediaWikiServices;
use SkinTemplate;

class AddEditBook implements SkinTemplateNavigation__UniversalHook {

	/**
	 * @param SkinTemplate $sktemplate
	 * @return bool
	 */
	protected function skipProcessing( SkinTemplate $sktemplate ) {
		$title = $sktemplate->getTitle();
		if ( !$title ) {
			return true;
		}

		if ( $title->getContentModel() !== 'book' ) {
			return true;
		}
		$user = $sktemplate->getUser();
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		$userCan = $permissionManager->userCan( 'edit', $user, $title );
		if ( !$userCan ) {
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

		// In case VisualEditor overrides with "Edit source"
		$links['views']['edit']['text'] = $sktemplate->msg( 'edit' )->text();
		$links['views']['edit']['title'] = $sktemplate->msg( 'edit' )->text();
		$links['views']['edit']['href'] = $sktemplate->getTitle()->getLocalURL( [
			'action' => 'edit',
		] );

		// Add real "Edit source"
		$links['views']['menueditsource'] = $links['views']['edit'];
		$links['views']['menueditsource']['id'] = 'ca-editbooksource';
		$links['views']['menueditsource']['text']
			= $sktemplate->msg( 'bs-bookshelf-action-editbook' )->plain();
		$links['views']['menueditsource']['href'] = $sktemplate->getTitle()->getLinkURL( [
			'action' => 'editbooksource'
		] );
	}
}
