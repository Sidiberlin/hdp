<?php

namespace BlueSpice\ProDistributionConnector\ContentDroplets;

use MediaWiki\Extension\ContentDroplets\Droplet\TemplateDroplet;
use Message;

class DecisionDroplet extends TemplateDroplet {

	/**
	 * Get target for the template
	 * @return string
	 */
	protected function getTarget(): string {
		return 'Decision';
	}

	/**
	 * Template params
	 * @return array
	 */
	protected function getParams(): array {
		return [
			'decision' => 'Use decision droplet'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): Message {
		return Message::newFromKey( 'bs-pro-distribution-droplet-decision-name' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return Message::newFromKey( 'bs-pro-distribution-droplet-decision-desc' );
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return 'droplet-decision';
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules(): array {
		return [ 'ext.prodistributionconnector.droplet.decision' ];
	}

	/**
	 * @inheritDoc
	 */
	public function getCategories(): array {
		return [ 'content', 'featured' ];
	}
}
