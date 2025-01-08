<?php

namespace BlueSpice\Expiry;

use MediaWiki\MediaWikiServices;
use Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\RestrictedTextLink;

class GlobalActionsEditing extends RestrictedTextLink {

	public function __construct() {
		parent::__construct( [] );
	}

	/**
	 *
	 * @return string
	 */
	public function getId(): string {
		return 'ga-special-expiry';
	}

	/**
	 *
	 * @return array
	 */
	public function getPermissions(): array {
		$permissions = MediaWikiServices::getInstance()
			->getSpecialPageFactory()
			->getPage( 'Expiry' );
		if ( !$permissions ) {
			return [];
		}
		return [ $permissions->getRestriction() ];
	}

	/**
	 *
	 * @return string
	 */
	public function getHref(): string {
		$tool = MediaWikiServices::getInstance()
		->getSpecialPageFactory()
		->getPage( 'Expiry' );
		if ( !$tool ) {
			return '';
		}
		return $tool->getPageTitle()->getLocalURL();
	}

	/**
	 *
	 * @return Message
	 */
	public function getText(): Message {
		return Message::newFromKey( 'specialexpiry' );
	}

	/**
	 *
	 * @return Message
	 */
	public function getTitle(): Message {
		return Message::newFromKey( 'specialexpiry' );
	}

	/**
	 *
	 * @return Message
	 */
	public function getAriaLabel(): Message {
		return Message::newFromKey( 'expiry' );
	}
}
