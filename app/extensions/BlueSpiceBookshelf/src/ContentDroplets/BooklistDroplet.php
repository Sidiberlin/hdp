<?php

namespace BlueSpice\Bookshelf\ContentDroplets;

use MediaWiki\Extension\ContentDroplets\Droplet\TagDroplet;
use Message;

class BooklistDroplet extends TagDroplet {

	/**
	 * @inheritDoc
	 */
	public function getName(): Message {
		return Message::newFromKey( 'bs-bookshelf-droplet-name' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return Message::newFromKey( "bs-bookshelf-droplet-description" );
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return 'droplet-book';
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules(): array {
		return [ 'ext.bluespice.booklist.visualEditorTagDefinition' ];
	}

	/**
	 * @return array
	 */
	public function getCategories(): array {
		return [ 'content', 'navigation', 'lists' ];
	}

	/**
	 *
	 * @return string
	 */
	protected function getTagName(): string {
		return 'bs:booklist';
	}

	/**
	 * @return array
	 */
	protected function getAttributes(): array {
		return [
			"filter" => 'title:test'
		];
	}

	/**
	 * @return bool
	 */
	protected function hasContent(): bool {
		return false;
	}

	/**
	 * @return string|null
	 */
	public function getVeCommand(): ?string {
		return 'booklistCommand';
	}

}
