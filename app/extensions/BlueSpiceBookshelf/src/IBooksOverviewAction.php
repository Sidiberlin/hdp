<?php

namespace BlueSpice\Bookshelf;

use MediaWiki\Message\Message;

interface IBooksOverviewAction {

	/**
	 * @return string
	 */
	public function getName(): string;

	/**
	 * @return int
	 */
	public function getPosition(): int;

	/**
	 * @return array
	 */
	public function getClasses(): array;

	/**
	 * @return array
	 */
	public function getIconClasses(): array;

	/**
	 * @return Message
	 */
	public function getText(): Message;

	/**
	 * @return Message
	 */
	public function getTitle(): Message;

	/**
	 * @return string
	 */
	public function getHref(): string;

	/**
	 * @return string
	 */
	public function getRequiredPermission(): string;

	/**
	 * @return array
	 */
	public function getRLModules(): array;
}
