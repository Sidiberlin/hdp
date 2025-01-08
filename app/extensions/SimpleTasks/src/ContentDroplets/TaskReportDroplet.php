<?php

namespace SimpleTasks\ContentDroplets;

use MediaWiki\Extension\ContentDroplets\Droplet\TagDroplet;
use Message;

class TaskReportDroplet extends TagDroplet {

	/**
	 * @inheritDoc
	 */
	public function getName(): Message {
		return Message::newFromKey( 'simple-tasks-droplet-taskreport-name' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return Message::newFromKey( 'simple-tasks-droplet-taskreport-description' );
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return 'droplet-taskreport';
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules(): array {
		return [ 'ext.simpletasks.taskreport.nodes', 'ext.simpletasks.taskreport.styles' ];
	}

	/**
	 * @return array
	 */
	public function getCategories(): array {
		return [ 'content', 'lists', 'featured' ];
	}

	/**
	 *
	 * @return string
	 */
	protected function getTagName(): string {
		return 'taskreport';
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
		return 'taskReportCommand';
	}

}
