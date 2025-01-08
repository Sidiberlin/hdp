<?php

namespace BlueSpice\ProDistributionConnector\ContentDroplets;

use MediaWiki\Extension\ContentDroplets\Droplet\TemplateDroplet;
use Message;

class ModalDialogDroplet extends TemplateDroplet {

	/**
	 * Get target for the template
	 * @return string
	 */
	protected function getTarget(): string {
		return 'ModalDialog';
	}

	/**
	 * Template params
	 * @return array
	 */
	protected function getParams(): array {
		return [
			'title' => 'Modal',
			'btnLabel' => 'label',
			'body' => 'Content modal body',
			'bgcolor' => 'blue',
			'footer' => ''
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): Message {
		return Message::newFromKey( 'bs-pro-distribution-droplet-modal-dialog-name' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return Message::newFromKey( 'bs-pro-distribution-droplet-modal-dialog-desc' );
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return 'droplet-modalDialog';
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules(): array {
		return [ 'ext.prodistributionconnector.droplet.modalDialog' ];
	}

	/**
	 * @inheritDoc
	 */
	public function getCategories(): array {
		return [ 'content' ];
	}
}
