<?php

namespace BlueSpice\Discovery\Component;

use IContextSource;
use Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\Literal;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleLink;
use RequestContext;
use SpecialPage;
use Title;
use User;

class UserButtonLogin extends SimpleLink {

	/**
	 *
	 * @var RequestContext
	 */
	private $requestContext = null;

	/**
	 *
	 * @param RequestContext $requestContext
	 */
	public function __construct( $requestContext ) {
		parent::__construct( [] );

		$this->requestContext = $requestContext;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'usr-login';
	}

	/**
	 * @inheritDoc
	 */
	public function getSubComponents(): array {
		return [
			new Literal(
				'usr-login-txt',
				Message::newFromKey( 'bs-discovery-navbar-user-login-text' )->text()
			)
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getRole(): string {
		return 'link';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): Message {
		return Message::newFromKey( 'bs-discovery-navbar-user-login-title' );
	}

	/**
	 * @inheritDoc
	 */
	public function getAriaLabel(): Message {
		return Message::newFromKey( 'bs-discovery-navbar-user-login-aria-label' );
	}

	/**
	 * @inheritDoc
	 */
	public function getHref(): string {
		$returnToPage = $this->getReturnToPage();
		$returnToAttrib = 'returnto=' . $returnToPage->getPrefixedDBkey();
		return Title::newFromText( 'Login', NS_SPECIAL )->getLocalURL( $returnToAttrib );
	}

	/**
	 *
	 * @param IContextSource $context
	 * @return bool
	 */
	public function shouldRender( IContextSource $context ): bool {
		/** @var User */
		$user = $context->getUser();
		if ( $user->isAnon() ) {
			return true;
		}

		return false;
	}

	/**
	 *
	 * @return Title
	 */
	private function getReturnToPage(): Title {
		$returnToPage = $this->requestContext->getTitle();
		if ( $returnToPage->equals( SpecialPage::getTitleFor( 'Badtitle' ) ) ) {
			$request = $this->requestContext;
			$requestTitle = Title::newFromText( $request->getRequest()->getVal( 'title', '' ) );
			if ( $requestTitle instanceof Title ) {
				$returnToPage = $requestTitle;
			} else {
				$returnToPage = Title::newMainPage();
			}
		}
		return $returnToPage;
	}
}
