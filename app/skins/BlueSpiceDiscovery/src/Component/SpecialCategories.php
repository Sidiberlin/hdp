<?php

namespace BlueSpice\Discovery\Component;

use Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\RestrictedTextLink;
use SpecialPage;

class SpecialCategories extends RestrictedTextLink {

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
		return 'special-categories';
	}

	/**
	 *
	 * @return string
	 */
	public function getHref(): string {
		/** @var Title */
		$specialpage = SpecialPage::getTitleFor( 'Categories' );

		return $specialpage->getLocalURL();
	}

	/**
	 *
	 * @return array
	 */
	public function getPermissions(): array {
		return [ 'read' ];
	}

	/**
	 *
	 * @return Message
	 */
	public function getText(): Message {
		return Message::newFromKey( 'bs-discovery-mainlinks-categories-label' );
	}

	/**
	 *
	 * @return Message
	 */
	public function getTitle(): Message {
		return Message::newFromKey( 'bs-discovery-mainlinks-categories-label' );
	}

	/**
	 *
	 * @return Message
	 */
	public function getAriaLabel(): Message {
		return Message::newFromKey( 'bs-discovery-mainlinks-categories-label' );
	}
}
