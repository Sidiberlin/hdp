<?php

namespace BlueSpice\Discovery\Component;

use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleDropdownItemlistFromArray;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleDropdownSplitButton;
use MWStake\MediaWiki\Component\CommonUserInterface\LinkFormatter;
use User;

class CreateContentSplitButton extends SimpleDropdownSplitButton {

	/**
	 *
	 * @var User
	 */
	private $user = null;

	/**
	 *
	 * @var PermissionManager
	 */
	private $permissionManager = null;

	/**
	 *
	 * @param User $user
	 * @param PermissionManager $permissionManager
	 */
	public function __construct( $user, $permissionManager ) {
		parent::__construct( [] );

		$this->user = $user;
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'new-content';
	}

	/**
	 * @inheritDoc
	 */
	public function getSubComponents(): array {
		return [
			new SimpleDropdownItemlistFromArray( [
				'id' => 'new-content-itms',
				'classes' => [],
				'links' => $this->getMenuLinks()
			] )
		];
	}

	/**
	 * @return array
	 */
	public function getContainerClasses(): array {
		return [ 'mx-2' ];
	}

	/**
	 * @return array
	 */
	public function getButtonClasses(): array {
		return [ 'mws-button-primary', 'new-page' ];
	}

		/**
		 * @return array
		 */
	public function getSplitButtonClasses(): array {
		return [ 'mws-button-primary' ];
	}

	/**
	 * @return array
	 */
	public function getMenuClasses(): array {
		return [ 'mws-dropdown-primary', 'dropdown-menu-end' ];
	}

	/**
	 * @return Message
	 */
	public function getButtonText(): Message {
		return Message::newFromKey( 'bs-discovery-header-create-button-text' );
	}

	/**
	 * @return Message
	 */
	public function getButtonTitle(): Message {
		return Message::newFromKey( 'bs-discovery-header-create-button-title' );
	}

	/**
	 * @return Message
	 */
	public function getSplitButtonTitle(): Message {
		return Message::newFromKey( 'bs-discovery-header-create-split-button-title' );
	}

	/**
	 * @return Message
	 */
	public function getButtonAriaLabel(): Message {
		return Message::newFromKey( 'bs-discovery-header-create-button-aria-label' );
	}

	/**
	 * @return Message
	 */
	public function getSplitButtonAriaLabel(): Message {
		return Message::newFromKey( 'bs-discovery-header-create-split-button-aria-label' );
	}

	/**
	 *
	 * @return bool
	 */
	public function buttonIsDisabled(): bool {
		if ( !isset( $this->componentProcessData['panel'] )
			|| !isset( $this->componentProcessData['panel']['create'] )
			|| !isset( $this->componentProcessData['panel']['create']['ca-new-page'] )
			|| empty( $this->componentProcessData['panel']['create']['ca-new-page'] ) ) {
				return true;
		}
		return false;
	}

		/**
		 *
		 * @return bool
		 */
	public function splitButtonIsDisabled(): bool {
		if ( !isset( $this->componentProcessData['panel'] )
			|| !isset( $this->componentProcessData['panel']['create'] )
			|| empty( $this->componentProcessData['panel']['create'] ) ) {
				return true;
		}
		return false;
	}

	/**
	 *
	 * @return array
	 */
	private function getMenuLinks(): array {
		$items = [];

		if ( !isset( $this->componentProcessData['panel'] )
			|| !isset( $this->componentProcessData['panel']['create'] ) ) {
				return $items;
		}
		$create = $this->componentProcessData['panel']['create'];
		if ( empty( $create ) ) {
			return $items;
		}

		$services = MediaWikiServices::getInstance();
		/** @var LinkFormatter */
		$linkFormatter = $services->getService( 'MWStakeLinkFormatter' );
		$items = $linkFormatter->formatLinks( $create );

		return $items;
	}

}
