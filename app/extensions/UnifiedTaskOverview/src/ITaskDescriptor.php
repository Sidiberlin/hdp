<?php

namespace MediaWiki\Extension\UnifiedTaskOverview;

use MediaWiki\Message\Message;

interface ITaskDescriptor {

	/**
	 *
	 * @return string
	 */
	public function getType(): string;

	/**
	 *
	 * @return string
	 */
	public function getURL(): string;

	/**
	 *
	 * @return Message
	 */
	public function getHeader(): Message;

	/**
	 *
	 * @return Message
	 */
	public function getSubHeader(): Message;

	/**
	 *
	 * @return Message
	 */
	public function getBody(): Message;

	/**
	 *
	 * @return int
	 */
	public function getSortKey(): int;

	/**
	 * @return array
	 */
	public function getRLModules(): array;
}
