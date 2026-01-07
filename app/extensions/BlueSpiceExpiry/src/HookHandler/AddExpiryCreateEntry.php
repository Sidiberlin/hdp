<?php

namespace BlueSpice\Expiry\HookHandler;

use BlueSpice\Expiry\Extension;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\MediaWikiServices;
use SkinTemplate;

class AddExpiryCreateEntry implements SkinTemplateNavigation__UniversalHook {

	/**
	 * @param SkinTemplate $sktemplate
	 * @return bool
	 */
	protected function skipProcessing( SkinTemplate $sktemplate ) {
		$title = $sktemplate->getTitle();

		if ( !$sktemplate->getUser()->isRegistered() ) {
			return true;
		}
		if ( !$title->exists() || $title->isSpecialPage() ) {
			return true;
		}
		$isAllowed = MediaWikiServices::getInstance()->getPermissionManager()->userCan(
			'expirearticle',
			$sktemplate->getUser(),
			$title
		);
		if ( !$isAllowed ) {
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

		$label = $sktemplate->msg( 'bs-expiry-menu-entry-create' )->text();
		$expiry = Extension::getExpiryForPage( $sktemplate->getTitle()->getArticleID(), false );

		if ( $expiry ) {
			$label = $sktemplate->msg( 'bs-expiry-menu-entry-manage' )->text();
		}
		$links['actions']['expiryCreate'] = [
			"class" => '',
			"text" => $label,
			"href" => "#",
			"bs-group" => "hidden",
			'position' => 20,
		];
	}
}
