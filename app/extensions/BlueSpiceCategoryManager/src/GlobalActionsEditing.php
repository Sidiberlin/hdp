<?php

namespace BlueSpice\CategoryManager;

use Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\RestrictedTextLink;
use SpecialPage;

class GlobalActionsEditing extends RestrictedTextLink {
	/**
	 *
	 */
	public function __construct() {
		parent::__construct( [] );
	}

	/**
	 *
	 * @return string
	 */
	public function getId(): string {
		return 'ga-bs-category';
	}

	/**
	 *
	 * @return string[]
	 */
	public function getPermissions(): array {
		$permissions = [
			'categorymanager-viewspecialpage'
		];
		return $permissions;
	}

	/**
	 * @return string
	 */
	public function getHref(): string {
		$specialPage = SpecialPage::getTitleFor( 'BlueSpiceCategoryManager' );
		return $specialPage->getLocalURL();
	}

	/**
	 * @return Message
	 */
	public function getText(): Message {
		return Message::newFromKey( 'bs-categorymanager-text' );
	}

	/**
	 * @return Message
	 */
	public function getTitle(): Message {
		return Message::newFromKey( 'bluespicecategorymanager-desc' );
	}

	/**
	 * @return Message
	 */
	public function getAriaLabel(): Message {
		return Message::newFromKey( 'bs-categorymanager-text' );
	}
}
