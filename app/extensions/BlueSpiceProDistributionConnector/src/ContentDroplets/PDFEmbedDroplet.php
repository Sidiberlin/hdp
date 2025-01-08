<?php

namespace BlueSpice\ProDistributionConnector\ContentDroplets;

use MediaWiki\Extension\ContentDroplets\Droplet\TagDroplet;
use Message;

class PDFEmbedDroplet extends TagDroplet {

	/**
	 * @inheritDoc
	 */
	public function getName(): Message {
		return Message::newFromKey( 'bs-pro-distribution-pdfembed-droplet-name' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return Message::newFromKey( 'bs-pro-distribution-pdfembed-droplet-description' );
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return 'droplet-pdfembed';
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules(): array {
		return [ 'ext.prodistributionconnector.droplet.pdfembed' ];
	}

	/**
	 * @return array
	 */
	public function getCategories(): array {
		return [ 'content', 'media' ];
	}

	/**
	 *
	 * @return string
	 */
	protected function getTagName(): string {
		return 'pdf';
	}

	/**
	 * @return array
	 */
	protected function getAttributes(): array {
		return [
			'width',
			'height',
			'page'
		];
	}

	/**
	 * @return bool
	 */
	protected function hasContent(): bool {
		return true;
	}

	/**
	 * @return string|null
	 */
	public function getVeCommand(): ?string {
		return 'pdfCommand';
	}
}
