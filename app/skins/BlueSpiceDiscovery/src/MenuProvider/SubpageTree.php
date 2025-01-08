<?php

namespace BlueSpice\Discovery\MenuProvider;

use BlueSpice\Discovery\Component\SubpageTreePanel;
use BlueSpice\Discovery\IMenuProvider;
use Message;
use MWStake\MediaWiki\Component\CommonUserInterface\IComponent;
use RawMessage;

class SubpageTree implements IMenuProvider {

	/**
	 * @return string
	 */
	public function getName(): string {
		return 'subpage-tree';
	}

	/**
	 * @return Message
	 */
	public function getLabelMsg(): Message {
		return new Message( 'bs-discovery-menu-provider-subpage-tree-label' );
	}

	/**
	 * @return Message
	 */
	public function getDescriptionMsg(): Message {
		return new RawMessage( 'bs-discovery-menu-provider-subpage-tree-desc' );
	}

	/**
	 * @return IComponent
	 */
	public function getComponent(): IComponent {
		return new SubpageTreePanel();
	}
}
