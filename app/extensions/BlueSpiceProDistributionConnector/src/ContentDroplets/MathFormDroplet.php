<?php

declare( strict_types = 1 );

namespace BlueSpice\ProDistributionConnector\ContentDroplets;

use MediaWiki\Extension\ContentDroplets\Droplet\GenericDroplet;
use MediaWiki\Message\Message;

class MathFormDroplet extends GenericDroplet {

	/**
	 */
	public function __construct() {
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): Message {
		return Message::newFromKey( 'bs-pro-distribution-droplet-math-name' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return Message::newFromKey( 'bs-pro-distribution-droplet-math-desc' );
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return 'droplet-math';
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules(): array {
		return [
			'ext.math.visualEditor',
			'ext.prodistributionconnector.droplet.math'
		];
	}

	/**
	 * @return array
	 */
	public function getCategories(): array {
		return [ 'content', 'visualization' ];
	}

	/**
	 * @inheritDoc
	 */
	public function getContent(): string {
		return '<math>+</math>';
	}

	/**
	 * @inheritDoc
	 */
	public function getVeCommand(): ?string {
		return 'mathDialog';
	}
}
