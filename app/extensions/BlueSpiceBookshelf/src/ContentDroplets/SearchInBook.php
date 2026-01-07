<?php

declare( strict_types = 1 );

namespace BlueSpice\Bookshelf\ContentDroplets;

use BS\ExtendedSearch\ContentDroplets\SearchDroplet;
use MediaWiki\Message\Message;

class SearchInBook extends SearchDroplet {

	/**
	 * @inheritDoc
	 */
	public function getName(): Message {
		return Message::newFromKey( 'bs-bookshelf-droplet-search-name' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return Message::newFromKey( 'bs-bookshelf-droplet-search-description' );
	}

	/**
	 * @return array
	 */
	protected function getAttributes(): array {
		return [];
	}

	/**
	 * @return string
	 */
	protected function getTagName(): string {
		return 'bs:searchinbook';
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return 'droplet-tag-search';
	}

	/**
	 * @return string|null
	 */
	public function getVeCommand(): ?string {
		return 'searchinbookCommand';
	}
}
