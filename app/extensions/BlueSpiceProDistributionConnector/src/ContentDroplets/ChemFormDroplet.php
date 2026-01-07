<?php

declare( strict_types = 1 );

namespace BlueSpice\ProDistributionConnector\ContentDroplets;

use MediaWiki\Extension\ContentDroplets\Droplet\GenericDroplet;
use MediaWiki\Message\Message;

class ChemFormDroplet extends GenericDroplet {

	/**
	 */
	public function __construct() {
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): Message {
		return Message::newFromKey( 'bs-pro-distribution-droplet-chem-name' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return Message::newFromKey( 'bs-pro-distribution-droplet-chem-desc' );
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return 'droplet-chem';
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules(): array {
		return [
			'ext.math.visualEditor',
			'ext.prodistributionconnector.droplet.chem'
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
		return '<chem>A + B</chem>';
	}

	/**
	 * @inheritDoc
	 */
	public function getVeCommand(): ?string {
		return 'chemDialog';
	}
}
