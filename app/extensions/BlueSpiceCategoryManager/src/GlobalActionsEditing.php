<?php

namespace BlueSpice\CategoryManager;

use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\RestrictedTextLink;

class GlobalActionsEditing extends RestrictedTextLink {

	public function __construct() {
		parent::__construct( [] );
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return 'ga-bs-category';
	}

	/** @inheritDoc */
	public function getPermissions(): array {
		return [ 'edit' ];
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
