<?php

namespace BlueSpice\Bookshelf\ContentDroplets;

use MediaWiki\Extension\ContentDroplets\Droplet\TemplateDroplet;
use Message;

class BookPDFDroplet extends TemplateDroplet {

	/**
	 * Get target for the template
	 * @return string
	 */
	protected function getTarget(): string {
		return 'BookPDFLink';
	}

	/**
	 * Template params
	 * @return array
	 */
	protected function getParams(): array {
		return [
			'book' => '',
			'template' => '',
			'label' => 'Book PDF Link'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): Message {
		return Message::newFromKey( 'bs-bookshelf-droplet-pdf-name' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return Message::newFromKey( "bs-bookshelf-droplet-pdf-description" );
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return 'droplet-pdf-book';
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules(): array {
		return [ 'ext.bluespice.bookshelf.droplet-bookpdf' ];
	}

	/**
	 * @return array
	 */
	public function getCategories(): array {
		return [ 'content', 'export' ];
	}
}
