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
	protected function shouldSkipProcessing( SkinTemplate $sktemplate ) {
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
		if ( $this->shouldSkipProcessing( $sktemplate ) ) {
			return;
		}

		$editQuery = [ 'action' => 'edit' ];
		$editSourceQuery = [ 'action' => 'editbooksource' ];
		$oldid = $sktemplate->getRequest()->getVal( 'oldid' );
		if ( $oldid !== null ) {
			$editQuery['oldid'] = $oldid;
			$editSourceQuery['oldid'] = $oldid;
		}

		// In case VisualEditor overrides with "Edit source"
		$links['views']['edit']['text'] = $sktemplate->msg( 'edit' )->text();
		$links['views']['edit']['title'] = $sktemplate->msg( 'edit' )->text();
		$links['views']['edit']['href'] = $sktemplate->getTitle()->getLocalURL( $editQuery );

		// Add real "Edit source"
		$links['views']['editbooksource'] = $links['views']['edit'];
		$links['views']['editbooksource']['id'] = 'ca-editbooksource';
		$links['views']['editbooksource']['text']
			= $sktemplate->msg( 'bs-bookshelf-action-editbook' )->text();
		$links['views']['editbooksource']['href'] = $sktemplate->getTitle()->getLinkURL(
			$editSourceQuery
		);
	}
}
