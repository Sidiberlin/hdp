<?php

namespace BlueSpice\Discovery\Component;

use Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\RestrictedTextLink;
use SpecialPage;

class SpecialRecentChanges extends RestrictedTextLink {

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
		return 'n-recentchanges';
	}

	/**
	 *
	 * @return string
	 */
	public function getHref(): string {
		/** @var Title */
		$specialpage = SpecialPage::getTitleFor( 'Recentchanges' );
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
		return new Message( 'recentchanges' );
	}

	/**
	 *
	 * @return Message
	 */
	public function getTitle(): Message {
		return new Message( 'recentchanges' );
	}

	/**
	 *
	 * @return Message
	 */
	public function getAriaLabel(): Message {
		return new Message( 'recentchanges' );
	}
}
