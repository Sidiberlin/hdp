<?php

namespace MediaWiki\Extension\DateTimeTools\ContentDroplets;

use MediaWiki\Extension\ContentDroplets\Droplet\TagDroplet;
use Message;

class DateDroplet extends TagDroplet {

	/**
	 * @inheritDoc
	 */
	public function getName(): Message {
		return Message::newFromKey( 'datetimetools-droplet-date-name' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return Message::newFromKey( 'datetimetools-droplet-date-description' );
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return 'droplet-date';
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules(): array {
		return [ 'ext.dateTimeTools.ve' ];
	}

	/**
	 * @return array
	 */
	public function getCategories(): array {
		return [ 'content' ];
	}

	/**
	 *
	 * @return string
	 */
	protected function getTagName(): string {
		return 'datetime';
	}

	/**
	 * @return array
	 */
	protected function getAttributes(): array {
		return [];
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
		return 'datetimeCommand';
	}
}
