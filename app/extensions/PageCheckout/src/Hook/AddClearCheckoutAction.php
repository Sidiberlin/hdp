<?php

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace MediaWiki\Extension\PageCheckout\Hook;

use MediaWiki\Extension\PageCheckout\CheckoutManager;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Permissions\PermissionManager;
use OutputPage;
use Skin;
use Title;
use User;

class AddClearCheckoutAction implements
	SkinTemplateNavigation__UniversalHook,
	BeforePageDisplayHook
{
	/** @var PermissionManager */
	private $permissionManager;
	/** @var CheckoutManager */
	private $manager;

	/**
	 * @param PermissionManager $permissionManager
	 * @param CheckoutManager $manager
	 */
	public function __construct( PermissionManager $permissionManager, CheckoutManager $manager ) {
		$this->permissionManager = $permissionManager;
		$this->manager = $manager;
	}

	/**
	 * @param \SkinTemplate $sktemplate
	 * @param array &$links
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !$this->shouldAdd( $sktemplate->getTitle(), $sktemplate->getUser() ) ) {
			return;
		}

		$links['actions']['pc_clear'] = [
			'text' => $sktemplate->getContext()->msg( 'pagecheckout-action-clear-label' )->text(),
			'href' => '#',
			'class' => false,
			'id' => 'ca-pc_clear'
		];
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @return bool
	 */
	private function shouldAdd( Title $title, User $user ) {
		if ( !$this->permissionManager->userHasRight( $user, 'page-checkout-clear' ) ) {
			return false;
		}

		if (
			!$title->exists() || $title->isSpecialPage() ||
			!$this->manager->isCheckedOut( $title )
		) {
			return false;
		}

		return true;
	}

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->shouldAdd( $out->getTitle(), $out->getUser() ) ) {
			return;
		}

		$out->addModules( 'ext.pagecheckout.clear' );
	}

}
