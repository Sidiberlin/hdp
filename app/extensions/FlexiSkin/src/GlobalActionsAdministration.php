<?php

namespace MediaWiki\Extension\FlexiSkin;

use Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\RestrictedTextLink;
use SpecialPage;

class GlobalActionsAdministration extends RestrictedTextLink {

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
		return 'ga-bs-flexiskin';
	}

	/**
	 *
	 * @return string[]
	 */
	public function getPermissions(): array {
		return [ 'flexiskin-viewspecialpage' ];
	}

	/**
	 * @return string
	 */
	public function getHref(): string {
		$specialPage = SpecialPage::getTitleFor( 'FlexiSkin' );
		return $specialPage->getLocalURL();
	}

	/**
	 * @return Message
	 */
	public function getText(): Message {
		return Message::newFromKey( 'flexiskin-label' );
	}

	/**
	 * @return Message
	 */
	public function getTitle(): Message {
		return Message::newFromKey( 'flexiskin-extension-desc' );
	}

	/**
	 * @return Message
	 */
	public function getAriaLabel(): Message {
		return Message::newFromKey( 'flexiskin-label' );
	}

}
