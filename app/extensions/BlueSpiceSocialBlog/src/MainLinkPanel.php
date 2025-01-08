<?php

namespace BlueSpice\Social\Blog;

use Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\RestrictedTextLink;
use SpecialPage;

class MainLinkPanel extends RestrictedTextLink {

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
		return 'bs-social-special-blog';
	}

	/**
	 *
	 * @return string[]
	 */
	public function getPermissions(): array {
		return [ 'read' ];
	}

	/**
	 * @return string
	 */
	public function getHref(): string {
		$specialPage = SpecialPage::getTitleFor( 'Blog' );
		return $specialPage->getLocalURL();
	}

	/**
	 * @return Message
	 */
	public function getText(): Message {
		return Message::newFromKey( 'bs-social-blog-mainlinks-label' );
	}

	/**
	 * @return Message
	 */
	public function getTitle(): Message {
		return Message::newFromKey( 'bs-social-blog-mainlinks-label' );
	}

	/**
	 * @return Message
	 */
	public function getAriaLabel(): Message {
		return Message::newFromKey( 'bs-social-blog-mainlinks-label' );
	}

}
