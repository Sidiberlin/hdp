<?php

namespace BlueSpice\ProDistributionConnector\ContentDroplets;

use MediaWiki\Extension\ContentDroplets\Droplet\TemplateDroplet;
use Message;

class ProConListDroplet extends TemplateDroplet {

	/**
	 * Get target for the template
	 * @return string
	 */
	protected function getTarget(): string {
		return 'ProConList';
	}

	/**
	 * Template params
	 * @return array
	 */
	protected function getParams(): array {
		return [
			'title-advantages' => 'Advantages',
			'title-disadvantages' => 'Disadvantages',
			'advantages' => '* ',
			'disadvantages' => '* '
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): Message {
		return Message::newFromKey( 'bs-pro-distribution-droplet-pro-con-list-name' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return Message::newFromKey( 'bs-pro-distribution-droplet-pro-con-list-desc' );
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return 'droplet-proconlist';
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules(): array {
		return [ 'ext.prodistributionconnector.droplet.proConList' ];
	}

	/**
	 * @inheritDoc
	 */
	public function getCategories(): array {
		return [ 'content', 'visualization' ];
	}
}
